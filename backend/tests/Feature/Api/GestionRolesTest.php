<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Soporte\InteractuaConAcceso;
use Tests\TestCase;

class GestionRolesTest extends TestCase
{
    use InteractuaConAcceso, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAcceso();
        $this->actuarComoRol('admin');
    }

    #[Test]
    public function los_roles_se_listan_con_sus_permisos_y_usuarios(): void
    {
        $this->getJson('/roles')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.nombre', 'admin')
            ->assertJsonPath('data.0.protegido', true)
            ->assertJsonPath('data.0.cantidad_usuarios', 1)
            ->assertJsonPath('data.2.nombre', 'consulta')
            ->assertJsonPath('data.2.permisos', ['equipos.ver', 'jugadores.ver', 'ligas.ver']);
    }

    #[Test]
    public function el_catalogo_de_permisos_incluye_su_equivalente_casl(): void
    {
        $this->getJson('/permisos')
            ->assertOk()
            ->assertJsonFragment(['nombre' => 'ligas.importar', 'accion' => 'importar', 'sujeto' => 'Liga']);
    }

    #[Test]
    public function un_admin_crea_edita_y_elimina_un_rol(): void
    {
        $id = $this->postJson('/roles', ['nombre' => 'Scouting', 'permisos' => ['jugadores.ver', 'equipos.ver']])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'scouting')
            ->json('data.id');

        $this->putJson("/roles/{$id}", ['nombre' => 'scouting', 'permisos' => ['jugadores.ver']])
            ->assertOk()
            ->assertJsonPath('data.permisos', ['jugadores.ver']);

        $this->deleteJson("/roles/{$id}")->assertNoContent();
        $this->assertNull(Role::find($id));
    }

    #[Test]
    public function se_rechazan_permisos_desconocidos_y_nombres_duplicados(): void
    {
        $this->assertErrorApi($this->postJson('/roles', ['nombre' => 'consulta', 'permisos' => ['todo']]), 422, 'validacion_fallida')
            ->assertJsonStructure(['error' => ['detalles' => ['campos' => ['nombre', 'permisos.0']]]]);
    }

    #[Test]
    public function el_rol_admin_no_se_modifica_y_un_rol_en_uso_no_se_elimina(): void
    {
        $admin = Role::findByName('admin', 'api');
        $consulta = Role::findByName('consulta', 'api');
        $this->usuarioConRol('consulta');

        $this->assertErrorApi($this->putJson("/roles/{$admin->id}", ['nombre' => 'admin', 'permisos' => []]), 409, 'conflicto');
        $this->assertErrorApi($this->deleteJson("/roles/{$admin->id}"), 409, 'conflicto');
        $this->assertErrorApi($this->deleteJson("/roles/{$consulta->id}"), 409, 'conflicto');
    }

    #[Test]
    public function los_cambios_de_permisos_aplican_en_la_siguiente_peticion(): void
    {
        $usuario = $this->usuarioConRol('consulta');
        $consulta = Role::findByName('consulta', 'api');

        $this->putJson("/roles/{$consulta->id}", ['nombre' => 'consulta', 'permisos' => ['ligas.ver']])->assertOk();

        $this->olvidarUsuarioAutenticado();
        $this->iniciarSesionComo($usuario);
        $this->assertErrorApi($this->getJson('/players'), 403, 'sin_permiso');
    }
}
