<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAcceso();
        $this->admin = $this->actuarComoRol('admin');
    }

    #[Test]
    public function un_admin_crea_un_usuario_con_roles_y_contrasena_con_hash(): void
    {
        $this->postJson('/usuarios', [
            'nombre' => 'Pedro Nuevo',
            'correo' => 'Pedro@IncaRail.test',
            'contrasena' => 'secreto123',
            'roles' => ['analista'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.correo', 'pedro@incarail.test')
            ->assertJsonPath('data.roles', ['analista'])
            ->assertJsonPath('data.activo', true)
            ->assertJsonMissingPath('data.contrasena');

        $usuario = Usuario::where('correo', 'pedro@incarail.test')->firstOrFail();
        $this->assertTrue(Hash::check('secreto123', $usuario->contrasena));
        $this->assertTrue($usuario->can('ligas.importar'));
    }

    #[Test]
    public function los_datos_del_usuario_se_validan(): void
    {
        $this->assertErrorApi($this->postJson('/usuarios', [
            'nombre' => '',
            'correo' => $this->admin->correo,
            'contrasena' => 'corta',
            'roles' => ['root'],
        ]), 422, 'validacion_fallida')
            ->assertJsonStructure(['error' => ['detalles' => ['campos' => ['nombre', 'correo', 'contrasena', 'roles.0']]]])
            ->assertJsonPath('error.detalles.campos.correo.0', 'El correo ya está en uso.');
    }

    #[Test]
    public function un_admin_edita_un_usuario_y_cambia_sus_roles(): void
    {
        $usuario = $this->usuarioConRol('consulta');

        $this->putJson("/usuarios/{$usuario->id}", ['nombre' => 'Renombrado', 'roles' => ['analista', 'consulta']])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Renombrado')
            ->assertJsonPath('data.roles', ['analista', 'consulta']);

        $this->putJson("/usuarios/{$usuario->id}", ['correo' => $usuario->correo])->assertOk();
    }

    #[Test]
    public function el_listado_de_usuarios_se_puede_buscar(): void
    {
        $this->usuarioConRol('consulta', ['nombre' => 'Rosa Buscable']);

        $this->getJson('/usuarios?buscar=buscable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Rosa Buscable');
    }

    #[Test]
    public function los_filtros_del_listado_de_usuarios_se_validan(): void
    {
        $this->assertErrorApi($this->getJson('/usuarios?por_pagina=500'), 422, 'validacion_fallida')
            ->assertJsonPath('error.detalles.campos.por_pagina.0', 'El campo tamaño de página debe estar entre 1 y 100.');
    }

    #[Test]
    public function desactivar_un_usuario_cierra_sus_sesiones_y_bloquea_nuevos_ingresos(): void
    {
        $usuario = $this->usuarioConRol('consulta');
        $token = $this->iniciarSesionComo($usuario);
        $this->withToken($this->iniciarSesionComo($this->admin));

        $this->patchJson("/usuarios/{$usuario->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->olvidarUsuarioAutenticado();
        $this->assertErrorApi($this->withToken($token)->getJson('/auth/sesion'), 401, 'no_autenticado');
        $this->assertErrorApi($this->postJson('/auth/iniciar-sesion', ['correo' => $usuario->correo, 'contrasena' => 'password']), 403, 'cuenta_desactivada');

        $this->withToken($this->iniciarSesionComo($this->admin));
        $this->patchJson("/usuarios/{$usuario->id}/activar")->assertOk()->assertJsonPath('data.activo', true);
        $this->postJson('/auth/iniciar-sesion', ['correo' => $usuario->correo, 'contrasena' => 'password'])->assertOk();
    }

    #[Test]
    public function un_admin_no_puede_dejar_a_todos_sin_acceso(): void
    {
        $this->assertErrorApi($this->patchJson("/usuarios/{$this->admin->id}/desactivar"), 409, 'conflicto');
        $this->assertErrorApi($this->putJson("/usuarios/{$this->admin->id}", ['roles' => ['consulta']]), 409, 'conflicto');
    }

    #[Test]
    public function un_usuario_inexistente_responde_404(): void
    {
        $this->assertErrorApi($this->getJson('/usuarios/99999'), 404, 'no_encontrado')
            ->assertJsonPath('error.mensaje', 'El usuario solicitado no existe.');
    }
}
