<?php

namespace Tests\Feature\Api;

use App\Aplicacion\Futbol\SincronizarCatalogoLigas;
use App\Dominio\Futbol\Eventos\LigaImportada;
use App\Infraestructura\FootballData\PresupuestoPeticiones;
use App\Jobs\ImportarLigaJob;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\Soporte\SimulaFootballData;
use Tests\TestCase;

class EndpointsFutbolTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase, SimulaFootballData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAcceso();
        config(['futbol.token' => 'token-de-prueba', 'futbol.url_base' => 'https://football.test/v4']);
        Queue::fake();
        $this->actuarComoRol('consulta');
    }

    #[Test]
    public function el_catalogo_se_sincroniza_una_vez_y_luego_se_responde_desde_la_base(): void
    {
        Http::fake(['football.test/v4/competitions' => Http::response($this->respuestaLigas())]);

        $this->getJson('/competitions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.desactualizado', false)
            ->assertJsonFragment(['codigo' => 'PL', 'disponible' => true, 'importada' => false]);

        $this->getJson('/competitions?buscar=libertadores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.disponible', false);

        Http::assertSentCount(1);
        $this->assertSame(2, Liga::count());
    }

    #[Test]
    public function el_listado_de_ligas_cuenta_los_equipos_desde_el_pivote(): void
    {
        SincronizarCatalogoLigas::marcarSincronizado();
        Liga::factory()->create(['codigo' => 'PL'])->equipos()->attach(Equipo::factory()->count(3)->create());
        Liga::factory()->create(['codigo' => 'PD']);

        $this->getJson('/competitions?buscar=P')
            ->assertOk()
            ->assertJsonFragment(['codigo' => 'PL', 'cantidad_equipos' => 3])
            ->assertJsonFragment(['codigo' => 'PD', 'cantidad_equipos' => 0]);
    }

    #[Test]
    public function el_listado_de_ligas_sobrevive_a_football_data_caido(): void
    {
        Liga::factory()->create(['codigo' => 'PL']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->getJson('/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.desactualizado', true);
    }

    #[Test]
    public function sin_datos_locales_y_con_football_data_caido_el_listado_responde_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertErrorApi($this->getJson('/competitions'), 503, 'football_data_no_disponible');
    }

    #[Test]
    public function un_error_del_proveedor_responde_502(): void
    {
        Http::fake(['*' => Http::response('Bad gateway', 500)]);

        $this->assertErrorApi($this->getJson('/competitions'), 502, 'football_data_error');
    }

    #[Test]
    public function la_falta_de_token_se_informa_explicitamente(): void
    {
        config(['futbol.token' => null]);

        $this->assertErrorApi($this->getJson('/competitions'), 503, 'football_data_sin_configurar');
    }

    #[Test]
    public function ver_una_liga_importa_sus_equipos_y_jugadores_una_sola_vez(): void
    {
        Http::fake(['football.test/v4/competitions/PL/teams' => Http::response($this->respuestaPlanteles())]);

        $this->getJson('/competitions/PL')
            ->assertOk()
            ->assertJsonPath('data.id', 2021)
            ->assertJsonPath('data.importada', true)
            ->assertJsonCount(2, 'data.equipos')
            ->assertJsonPath('data.equipos.0.nombre', 'Arsenal FC')
            ->assertJsonPath('data.equipos.0.cantidad_jugadores', 2)
            ->assertJsonPath('data.ultima_importacion.cantidad_jugadores', 3)
            ->assertJsonPath('meta.fuente', 'football-data');

        $this->getJson('/competitions/2021')
            ->assertOk()
            ->assertJsonPath('meta.fuente', 'base_de_datos');

        Http::assertSentCount(1);
        $this->assertSame(2, Equipo::count());
        $this->assertSame(3, Jugador::count());
    }

    #[Test]
    public function una_liga_inexistente_responde_404(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The resource you are looking for does not exist.', 'errorCode' => 404], 404)]);

        $this->assertErrorApi($this->getJson('/competitions/9999'), 404, 'liga_no_encontrada');
    }

    #[Test]
    public function una_liga_fuera_del_plan_se_rechaza_sin_gastar_peticiones(): void
    {
        Http::fake();
        Liga::factory()->create(['id' => 2152, 'codigo' => 'CLI', 'plan' => 'TIER_FOUR']);

        $this->assertErrorApi($this->getJson('/competitions/CLI'), 403, 'liga_fuera_del_plan');
        Http::assertNothingSent();
    }

    #[Test]
    public function la_undecima_importacion_del_minuto_no_se_envia_y_queda_en_cola_con_429(): void
    {
        Http::fake(['*' => Http::response($this->respuestaPlanteles())]);

        // Diez ligas distintas consumen todo el presupuesto...
        for ($id = 1; $id <= 10; $id++) {
            $this->getJson("/competitions/{$id}")->assertOk();
            Liga::query()->whereKey(2021)->update(['equipos_sincronizados_en' => null]);
        }

        // ...la undécima nunca llega a football-data: queda en cola y responde 429.
        $this->assertErrorApi($this->getJson('/competitions/SA'), 429, 'football_data_limite_alcanzado')
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.detalles.en_cola', true);

        Http::assertSentCount(10);
        Queue::assertPushed(ImportarLigaJob::class, fn (ImportarLigaJob $job) => $job->liga === 'SA');
    }

    #[Test]
    public function sin_cupo_una_liga_vencida_igual_se_sirve_desde_la_base(): void
    {
        Http::fake();
        $liga = Liga::factory()->create(['id' => 2021, 'codigo' => 'PL', 'equipos_sincronizados_en' => now()->subDays(3)]);
        $liga->equipos()->attach(Equipo::factory()->create());
        $this->app->make(PresupuestoPeticiones::class)->agotarPor(40);

        $this->getJson('/competitions/PL')
            ->assertOk()
            ->assertJsonPath('meta.desactualizado', true)
            ->assertJsonPath('meta.reintentar_en', 40)
            ->assertJsonCount(1, 'data.equipos');

        Http::assertNothingSent();
        Queue::assertPushed(ImportarLigaJob::class);
    }

    #[Test]
    public function los_equipos_guardados_se_listan_buscan_y_paginan(): void
    {
        $liga = Liga::factory()->create();
        Equipo::factory()->count(3)->create();
        $arsenal = Equipo::factory()->create(['nombre' => 'Arsenal FC']);
        $liga->equipos()->attach($arsenal);

        $this->getJson('/team?por_pagina=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4);

        $this->getJson('/team?buscar=arsenal')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/team?liga_id={$liga->id}")->assertOk()->assertJsonPath('data.0.nombre', 'Arsenal FC');
        $this->assertErrorApi($this->getJson('/team?por_pagina=1000'), 422, 'validacion_fallida');

        Equipo::factory()->create(['nombre' => 'England', 'tipo' => 'seleccion']);
        $this->getJson('/team?tipo=seleccion')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'England')->assertJsonPath('data.0.tipo', 'seleccion');
        $this->getJson('/team?tipo=club')->assertOk()->assertJsonPath('meta.total', 4);
        $this->assertErrorApi($this->getJson('/team?tipo=otro'), 422, 'validacion_fallida');
    }

    #[Test]
    public function el_detalle_de_un_equipo_incluye_sus_jugadores_y_ligas(): void
    {
        $equipo = Equipo::factory()->create(['nombre' => 'Arsenal FC']);
        Jugador::factory()->hasAttached($equipo, [], 'equipos')->create(['nombre' => 'David Raya', 'posicion' => 'Goalkeeper', 'numero_camiseta' => 22]);
        $equipo->ligas()->attach(Liga::factory()->create(['codigo' => 'PL']), ['temporada' => '2026']);

        $this->getJson("/team/{$equipo->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Arsenal FC')
            ->assertJsonPath('data.jugadores.0.nombre', 'David Raya')
            ->assertJsonPath('data.jugadores.0.numero_camiseta', 22)
            ->assertJsonPath('data.ligas.0.codigo', 'PL');
    }

    #[Test]
    public function un_equipo_o_jugador_inexistente_responde_404_con_un_mensaje_claro(): void
    {
        $this->assertErrorApi($this->getJson('/team/123456'), 404, 'no_encontrado')
            ->assertJsonPath('error.mensaje', 'El equipo solicitado no existe.');

        $this->assertErrorApi($this->getJson('/players/123456'), 404, 'no_encontrado')
            ->assertJsonPath('error.mensaje', 'El jugador solicitado no existe.');
    }

    #[Test]
    public function los_jugadores_guardados_se_filtran_y_ordenan(): void
    {
        $equipo = Equipo::factory()->create();
        Jugador::factory()->hasAttached($equipo, [], 'equipos')->create(['nombre' => 'Zack Keeper', 'posicion' => 'Goalkeeper']);
        Jugador::factory()->hasAttached($equipo, [], 'equipos')->create(['nombre' => 'Adam Striker', 'posicion' => 'Offence']);
        Jugador::factory()->create(['nombre' => 'Other Keeper', 'posicion' => 'Goalkeeper']);

        $this->getJson('/players')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.nombre', 'Adam Striker')
            ->assertJsonPath('filtros.posiciones', ['Goalkeeper', 'Offence']);

        $this->getJson("/players?posicion=Goalkeeper&equipo_id={$equipo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Zack Keeper');

        $this->getJson('/players?orden=nombre&direccion=desc')->assertOk()->assertJsonPath('data.0.nombre', 'Zack Keeper');
        $this->assertErrorApi($this->getJson('/players?orden=contrasena'), 422, 'validacion_fallida');
    }

    #[Test]
    public function las_posiciones_quedan_en_cache_hasta_una_nueva_importacion(): void
    {
        Jugador::factory()->create(['posicion' => 'Goalkeeper']);
        $this->getJson('/players')->assertJsonPath('filtros.posiciones', ['Goalkeeper']);

        Jugador::factory()->create(['posicion' => 'Offence']);
        $this->getJson('/players')->assertJsonPath('filtros.posiciones', ['Goalkeeper']);

        Liga::factory()->create(['id' => 2021]);
        event(new LigaImportada(2021, 1, 1, 'peticion'));
        $this->getJson('/players')->assertJsonPath('filtros.posiciones', ['Goalkeeper', 'Offence']);
    }

    #[Test]
    public function lo_guardado_se_sirve_incluso_sin_token_de_football_data(): void
    {
        config(['futbol.token' => null]);
        Http::fake();
        Jugador::factory()->create();

        $this->getJson('/team')->assertOk();
        $this->getJson('/players')->assertOk()->assertJsonCount(1, 'data');
        Http::assertNothingSent();
    }
}
