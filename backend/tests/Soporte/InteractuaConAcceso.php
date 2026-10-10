<?php

namespace Tests\Soporte;

use App\Models\Usuario;
use Database\Seeders\ClientePassportSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Testing\TestResponse;

/** Siembra la ACL y emite tokens reales de Passport, tal como los recibe la SPA. */
trait InteractuaConAcceso
{
    protected function prepararAcceso(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        $this->seed([RolesYPermisosSeeder::class, ClientePassportSeeder::class]);
    }

    protected function usuarioConRol(string $rol, array $atributos = []): Usuario
    {
        return tap(Usuario::factory()->create($atributos), fn (Usuario $usuario) => $usuario->assignRole($rol));
    }

    /** Inicia sesión por la API y recuerda el token para las siguientes llamadas. */
    protected function iniciarSesionComo(Usuario|string $usuario, string $contrasena = 'password'): string
    {
        $correo = $usuario instanceof Usuario ? $usuario->correo : $usuario;

        $token = $this->postJson('/auth/iniciar-sesion', ['correo' => $correo, 'contrasena' => $contrasena])
            ->assertOk()
            ->json('token_acceso');

        $this->withToken($token);

        return $token;
    }

    /** Inicia sesión como un usuario nuevo con el rol indicado. */
    protected function actuarComoRol(string $rol): Usuario
    {
        $usuario = $this->usuarioConRol($rol);
        $this->iniciarSesionComo($usuario);

        return $usuario;
    }

    /** Laravel conserva el usuario resuelto entre peticiones de una misma prueba: se olvida. */
    protected function olvidarUsuarioAutenticado(): void
    {
        $this->app['auth']->forgetGuards();
    }

    protected function assertErrorApi(TestResponse $respuesta, int $estado, string $codigo): TestResponse
    {
        return $respuesta
            ->assertStatus($estado)
            ->assertJsonPath('error.estado', $estado)
            ->assertJsonPath('error.codigo', $codigo)
            ->assertJsonStructure(['error' => ['estado', 'codigo', 'mensaje']]);
    }
}
