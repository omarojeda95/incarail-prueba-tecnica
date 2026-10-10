<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAcceso();
    }

    #[Test]
    public function el_inicio_de_sesion_entrega_un_token_bearer_el_usuario_y_sus_reglas_casl(): void
    {
        $usuario = $this->usuarioConRol('consulta', ['correo' => 'carla@incarail.test']);

        $this->postJson('/auth/iniciar-sesion', ['correo' => 'CARLA@incarail.test', 'contrasena' => 'password'])
            ->assertOk()
            ->assertJsonPath('tipo_token', 'Bearer')
            ->assertJsonPath('usuario.correo', $usuario->correo)
            ->assertJsonPath('usuario.roles', ['consulta'])
            ->assertJsonStructure(['token_acceso', 'expira_en'])
            ->assertJsonPath('habilidades', [
                ['action' => 'ver', 'subject' => 'Equipo'],
                ['action' => 'ver', 'subject' => 'Jugador'],
                ['action' => 'ver', 'subject' => 'Liga'],
            ]);
    }

    #[Test]
    public function las_contrasenas_se_guardan_con_hash(): void
    {
        $usuario = Usuario::factory()->create(['contrasena' => 'secreto-123']);

        $this->assertNotSame('secreto-123', $usuario->getRawOriginal('contrasena'));
        $this->assertTrue(Hash::check('secreto-123', $usuario->getRawOriginal('contrasena')));
    }

    #[Test]
    public function credenciales_incorrectas_responden_401(): void
    {
        $usuario = $this->usuarioConRol('consulta');

        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => $usuario->correo, 'contrasena' => 'no']), 401, 'credenciales_invalidas');
        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => 'nadie@incarail.test', 'contrasena' => 'no']), 401, 'credenciales_invalidas');
    }

    #[Test]
    public function datos_de_inicio_de_sesion_invalidos_responden_422_con_errores_por_campo(): void
    {
        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => 'no-es-un-correo']), 422, 'validacion_fallida')
            ->assertJsonStructure(['error' => ['detalles' => ['campos' => ['correo', 'contrasena']]]]);
    }

    #[Test]
    public function un_usuario_desactivado_no_puede_iniciar_sesion(): void
    {
        $usuario = $this->usuarioConRol('consulta', ['activo' => false]);

        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => $usuario->correo, 'contrasena' => 'password']), 403, 'cuenta_desactivada');
    }

    #[Test]
    public function toda_ruta_salvo_el_inicio_de_sesion_exige_token(): void
    {
        foreach (['/auth/sesion', '/competitions', '/competitions/PL', '/team', '/team/1', '/players', '/usuarios', '/roles'] as $ruta) {
            $this->assertErrorApi($this->getJson($ruta), 401, 'no_autenticado');
        }
    }

    #[Test]
    public function un_token_invalido_se_rechaza(): void
    {
        $this->assertErrorApi($this->withToken('token-falso')->getJson('/auth/sesion'), 401, 'no_autenticado');
    }

    #[Test]
    public function cerrar_sesion_revoca_el_token(): void
    {
        $usuario = $this->usuarioConRol('consulta');
        $token = $this->iniciarSesionComo($usuario);

        $this->getJson('/auth/sesion')->assertOk()->assertJsonPath('usuario.id', $usuario->id);
        $this->postJson('/auth/cerrar-sesion')->assertNoContent();

        $this->olvidarUsuarioAutenticado();
        $this->assertErrorApi($this->withToken($token)->getJson('/auth/sesion'), 401, 'no_autenticado');
    }

    #[Test]
    public function un_usuario_desactivado_con_sesion_abierta_pierde_el_acceso_al_instante(): void
    {
        $usuario = $this->usuarioConRol('consulta');
        $this->iniciarSesionComo($usuario);

        $usuario->forceFill(['activo' => false])->save();
        $this->olvidarUsuarioAutenticado();

        $this->assertErrorApi($this->getJson('/auth/sesion'), 403, 'cuenta_desactivada');
    }

    #[Test]
    public function los_intentos_de_inicio_de_sesion_se_limitan(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/iniciar-sesion', ['correo' => 'fuerza@incarail.test', 'contrasena' => 'intento'.$i]);
        }

        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => 'fuerza@incarail.test', 'contrasena' => 'otra']), 429, 'demasiadas_solicitudes')
            ->assertHeader('Retry-After');
    }
}
