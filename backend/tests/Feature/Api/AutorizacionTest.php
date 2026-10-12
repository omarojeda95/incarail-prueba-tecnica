<?php

namespace Tests\Feature\Api;

use App\Aplicacion\Futbol\SincronizarCatalogoLigas;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

/** Lo que puede hacer cada rol sembrado lo decide la API, ruta por ruta. */
class AutorizacionTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAcceso();
        Queue::fake();

        $liga = Liga::factory()->importada()->create(['id' => 2021, 'codigo' => 'PL']);
        $equipo = Equipo::factory()->create(['id' => 57]);
        $liga->equipos()->attach($equipo);
        Jugador::factory()->hasAttached($equipo, [], 'equipos')->create(['id' => 7]);
        SincronizarCatalogoLigas::marcarSincronizado();
    }

    /** @return array<string, array{string, string, string, array<string, int>}> */
    public static function matriz(): array
    {
        return [
            'listar ligas' => ['GET', '/competitions', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'ver liga' => ['GET', '/competitions/PL', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'encolar importación' => ['POST', '/competitions/PL/importar', '', ['admin' => 202, 'analista' => 202, 'consulta' => 403]],
            'listar equipos' => ['GET', '/team', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'ver equipo' => ['GET', '/team/57', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'listar jugadores' => ['GET', '/players', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'listar usuarios' => ['GET', '/usuarios', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
            'crear usuario' => ['POST', '/usuarios', 'usuario', ['admin' => 201, 'analista' => 403, 'consulta' => 403]],
            'listar roles' => ['GET', '/roles', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
            'crear rol' => ['POST', '/roles', 'rol', ['admin' => 201, 'analista' => 403, 'consulta' => 403]],
            'listar permisos' => ['GET', '/permisos', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
        ];
    }

    /** @param  array<string, int>  $esperado */
    #[Test]
    #[DataProvider('matriz')]
    public function cada_rol_obtiene_exactamente_sus_permisos(string $metodo, string $ruta, string $cuerpo, array $esperado): void
    {
        foreach ($esperado as $rol => $estado) {
            $this->olvidarUsuarioAutenticado();
            $this->actuarComoRol($rol);

            $datos = match ($cuerpo) {
                'usuario' => ['nombre' => 'Nuevo', 'correo' => "nuevo-{$rol}@incarail.test", 'contrasena' => 'secreto123', 'roles' => ['consulta']],
                'rol' => ['nombre' => "rol-{$rol}", 'permisos' => ['equipos.ver']],
                default => [],
            };

            $respuesta = $this->json($metodo, $ruta, $datos);

            $this->assertSame($estado, $respuesta->status(), "{$rol} {$metodo} {$ruta}: ".$respuesta->getContent());
            if ($estado === 403) {
                $this->assertErrorApi($respuesta, 403, 'sin_permiso');
            }
        }
    }
}
