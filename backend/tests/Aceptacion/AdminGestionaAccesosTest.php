<?php

namespace Tests\Aceptacion;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

/**
 * "Como administrador creo un rol y un usuario con ese rol; el usuario nuevo
 * solo ve lo que el rol permite, y pierde el acceso en cuanto lo desactivo."
 */
class AdminGestionaAccesosTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    #[Test]
    public function el_admin_otorga_y_revoca_accesos(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
        $this->seed(DatabaseSeeder::class);

        // 1. El admin crea un rol "scouting" que solo puede ver jugadores.
        $tokenAdmin = $this->iniciarSesionComo('admin@incarail.test');
        $this->postJson('/roles', ['nombre' => 'scouting', 'permisos' => ['jugadores.ver']])->assertCreated();

        // 2. ...y un usuario con ese rol.
        $usuarioId = $this->postJson('/usuarios', [
            'nombre' => 'Sofía Scout',
            'correo' => 'sofia@incarail.test',
            'contrasena' => 'scout2026',
            'roles' => ['scouting'],
        ])->assertCreated()->json('data.id');

        // 3. Sofía inicia sesión y recibe exactamente una regla CASL.
        $this->olvidarUsuarioAutenticado();
        $tokenSofia = $this->postJson('/auth/iniciar-sesion', ['correo' => 'sofia@incarail.test', 'contrasena' => 'scout2026'])
            ->assertOk()
            ->assertJsonPath('habilidades', [['action' => 'ver', 'subject' => 'Jugador']])
            ->json('token_acceso');

        $this->withToken($tokenSofia)->getJson('/players')->assertOk();
        $this->assertErrorApi($this->withToken($tokenSofia)->getJson('/team'), 403, 'sin_permiso');

        // 4. El admin la desactiva: su sesión abierta deja de funcionar.
        $this->olvidarUsuarioAutenticado();
        $this->withToken($tokenAdmin)->patchJson("/usuarios/{$usuarioId}/desactivar")->assertOk();

        $this->olvidarUsuarioAutenticado();
        $this->assertErrorApi($this->withToken($tokenSofia)->getJson('/players'), 401, 'no_autenticado');
    }
}
