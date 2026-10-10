<?php

namespace Tests\Unit\Infraestructura;

use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;
use App\Infraestructura\FootballData\ClienteFootballData;
use App\Infraestructura\FootballData\PresupuestoPeticiones;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\SimulaFootballData;
use Tests\TestCase;

class ClienteFootballDataTest extends TestCase
{
    use SimulaFootballData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['futbol.token' => 'token-de-prueba', 'futbol.url_base' => 'https://football.test/v4']);
    }

    #[Test]
    public function mapea_el_listado_de_ligas_y_se_autentica_con_el_token(): void
    {
        Http::fake(['football.test/v4/competitions' => Http::response($this->respuestaLigas())]);

        $ligas = $this->cliente()->ligas();

        $this->assertCount(2, $ligas);
        $this->assertSame(2021, $ligas[0]->id);
        $this->assertSame('PL', $ligas[0]->codigo);
        $this->assertSame('England', $ligas[0]->areaNombre);
        $this->assertSame('TIER_ONE', $ligas[0]->plan);
        $this->assertSame('2026-08-15', $ligas[0]->temporadaInicio?->format('Y-m-d'));
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Auth-Token', 'token-de-prueba'));
    }

    #[Test]
    public function mapea_una_liga_con_sus_equipos_y_planteles(): void
    {
        Http::fake(['football.test/v4/competitions/PL/teams' => Http::response($this->respuestaPlanteles())]);

        $resultado = $this->cliente()->plantelesDeLiga('PL');

        $this->assertSame(2021, $resultado->liga->id);
        $this->assertSame('2026', $resultado->temporada);
        $this->assertCount(2, $resultado->equipos);
        $this->assertSame('Arsenal FC', $resultado->equipos[0]->nombre);
        $this->assertSame('Mikel Arteta', $resultado->equipos[0]->entrenador);
        $this->assertSame('David Raya', $resultado->equipos[0]->plantel[0]->nombre);
        $this->assertSame('Goalkeeper', $resultado->equipos[0]->plantel[0]->posicion);
        $this->assertSame(22, $resultado->equipos[0]->plantel[0]->numeroCamiseta);
        $this->assertNull($resultado->equipos[0]->plantel[1]->numeroCamiseta);
        $this->assertSame(3, $resultado->cantidadJugadores());
    }

    #[Test]
    public function no_llama_al_proveedor_si_el_presupuesto_esta_agotado(): void
    {
        Http::fake();
        $this->app->make(PresupuestoPeticiones::class)->agotarPor(30);

        try {
            $this->cliente()->ligas();
            $this->fail('Se esperaba que el límite se aplicara localmente.');
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->assertSame(30, $e->reintentarEn);
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function confia_en_las_cabeceras_del_proveedor_cuando_informan_que_no_quedan_peticiones(): void
    {
        Http::fake(['*' => Http::response($this->respuestaLigas(), 200, [
            'X-Requests-Available-Minute' => '0',
            'X-RequestCounter-Reset' => '42',
        ])]);

        $this->cliente()->ligas();

        $this->assertSame(42, $this->app->make(PresupuestoPeticiones::class)->segundosParaReintentar());
    }

    #[Test]
    public function un_429_del_proveedor_es_un_limite_alcanzado_y_bloquea_el_presupuesto(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Too many requests'], 429, ['X-RequestCounter-Reset' => '17'])]);

        try {
            $this->cliente()->ligas();
            $this->fail('Se esperaba LimiteDePeticionesAlcanzado.');
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->assertSame(17, $e->reintentarEn);
        }

        $this->assertSame(0, $this->app->make(PresupuestoPeticiones::class)->restantes());
    }

    /** @return array<string, array{int, class-string}> */
    public static function erroresDelProveedor(): array
    {
        return [
            'no existe' => [404, RecursoNoEncontradoEnProveedor::class],
            'petición inválida' => [400, RecursoNoEncontradoEnProveedor::class],
            'fuera de nuestro plan' => [403, LigaFueraDelPlan::class],
            'error del servidor' => [500, ProveedorNoDisponible::class],
            'token inválido' => [401, ProveedorNoDisponible::class],
        ];
    }

    /** @param  class-string<\Throwable>  $excepcion */
    #[Test]
    #[DataProvider('erroresDelProveedor')]
    public function traduce_los_errores_del_proveedor_a_excepciones_de_dominio(int $estado, string $excepcion): void
    {
        Http::fake(['*' => Http::response(['message' => 'error'], $estado)]);

        $this->expectException($excepcion);

        $this->cliente()->plantelesDeLiga(9999);
    }

    #[Test]
    public function una_falla_de_conexion_se_informa_como_no_disponible(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        try {
            $this->cliente()->ligas();
            $this->fail('Se esperaba ProveedorNoDisponible.');
        } catch (ProveedorNoDisponible $e) {
            $this->assertTrue($e->sinConexion);
        }
    }

    #[Test]
    public function no_funciona_sin_token(): void
    {
        config(['futbol.token' => null]);
        Http::fake();

        try {
            $this->cliente()->ligas();
            $this->fail('Se esperaba ProveedorNoDisponible.');
        } catch (ProveedorNoDisponible $e) {
            $this->assertTrue($e->sinConfigurar);
        }

        Http::assertNothingSent();
    }

    private function cliente(): ClienteFootballData
    {
        return $this->app->make(ClienteFootballData::class);
    }
}
