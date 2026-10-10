<?php

namespace Tests\Aceptacion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\Soporte\SimulaFootballData;
use Tests\TestCase;

/**
 * "Como analista inicio sesión, busco la Premier League, la abro para que se
 * guarden sus equipos y jugadores, los recorro, y sigo trabajando cuando
 * football-data deja de responder." Usa los seeders reales y el usuario documentado.
 */
class AnalistaExploraUnaLigaTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase, SimulaFootballData;

    #[Test]
    public function el_analista_importa_una_liga_y_la_recorre_aunque_football_data_se_caiga(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        config(['futbol.token' => 'token-de-prueba', 'futbol.url_base' => 'https://football.test/v4']);
        Http::fake([
            'football.test/v4/competitions' => Http::response($this->respuestaLigas()),
            'football.test/v4/competitions/2021/teams' => Http::response($this->respuestaPlanteles()),
        ]);

        // 1. Inicia sesión con el usuario de prueba documentado.
        $sesion = $this->postJson('/auth/iniciar-sesion', ['correo' => 'analista@incarail.test', 'contrasena' => 'password'])->assertOk();
        $this->withToken($sesion->json('token_acceso'));
        $this->assertContains(['action' => 'importar', 'subject' => 'Liga'], $sesion->json('habilidades'));

        // 2. Busca la liga entre las disponibles.
        $this->getJson('/competitions?disponibles=1&buscar=premier')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.importada', false);

        // 3. La abre: se guardan sus equipos y jugadores.
        $this->getJson('/competitions/2021')->assertOk()->assertJsonCount(2, 'data.equipos');

        // 4. Recorre lo guardado.
        $equipoId = $this->getJson('/team?buscar=arsenal')->assertOk()->json('data.0.id');
        $this->getJson("/team/{$equipoId}")->assertOk()->assertJsonCount(2, 'data.jugadores');
        $this->getJson('/players?posicion=Goalkeeper')->assertOk()->assertJsonPath('data.0.nombre', 'David Raya');

        // 5. football-data se cae: todo lo guardado sigue funcionando.
        Http::fake(fn () => throw new ConnectionException('caído'));
        $this->getJson('/competitions/2021')->assertOk()->assertJsonPath('meta.fuente', 'base_de_datos');
        $this->getJson('/competitions')->assertOk();
        $this->getJson('/players')->assertOk()->assertJsonPath('meta.total', 3);

        // 6. El analista no puede administrar usuarios.
        $this->assertErrorApi($this->getJson('/usuarios'), 403, 'sin_permiso');

        // 7. Cierra sesión: el token deja de servir.
        $this->postJson('/auth/cerrar-sesion')->assertNoContent();
        $this->olvidarUsuarioAutenticado();
        $this->assertErrorApi($this->getJson('/team'), 401, 'no_autenticado');
    }
}
