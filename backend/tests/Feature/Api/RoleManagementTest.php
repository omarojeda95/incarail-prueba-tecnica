<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccess();
        $this->actingAsRole('admin');
    }

    #[Test]
    public function roles_are_listed_with_their_permissions_and_users(): void
    {
        $this->getJson('/roles')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'admin')
            ->assertJsonPath('data.0.protected', true)
            ->assertJsonPath('data.0.users_count', 1)
            ->assertJsonPath('data.2.name', 'consulta')
            ->assertJsonPath('data.2.permissions', ['competitions.read', 'players.read', 'teams.read']);
    }

    #[Test]
    public function the_permission_catalog_includes_the_casl_equivalent(): void
    {
        $this->getJson('/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'competitions.import', 'action' => 'import', 'subject' => 'Competition']);
    }

    #[Test]
    public function an_admin_creates_edits_and_deletes_a_role(): void
    {
        $id = $this->postJson('/roles', ['name' => 'Scouting', 'permissions' => ['players.read', 'teams.read']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'scouting')
            ->json('data.id');

        $this->putJson("/roles/{$id}", ['name' => 'scouting', 'permissions' => ['players.read']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['players.read']);

        $this->deleteJson("/roles/{$id}")->assertNoContent();
        $this->assertNull(Role::find($id));
    }

    #[Test]
    public function unknown_permissions_and_duplicated_names_are_rejected(): void
    {
        $this->assertApiError($this->postJson('/roles', ['name' => 'consulta', 'permissions' => ['everything']]), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['name', 'permissions.0']]]]);
    }

    #[Test]
    public function the_admin_role_cannot_be_changed_and_roles_in_use_cannot_be_deleted(): void
    {
        $admin = Role::findByName('admin', 'api');
        $consulta = Role::findByName('consulta', 'api');
        $this->userWithRole('consulta');

        $this->assertApiError($this->putJson("/roles/{$admin->id}", ['name' => 'admin', 'permissions' => []]), 409, 'conflict');
        $this->assertApiError($this->deleteJson("/roles/{$admin->id}"), 409, 'conflict');
        $this->assertApiError($this->deleteJson("/roles/{$consulta->id}"), 409, 'conflict');
    }

    #[Test]
    public function permission_changes_apply_to_the_next_request(): void
    {
        $user = $this->userWithRole('consulta');
        $consulta = Role::findByName('consulta', 'api');

        $this->putJson("/roles/{$consulta->id}", ['name' => 'consulta', 'permissions' => ['competitions.read']])->assertOk();

        $this->forgetAuthenticatedUser();
        $this->loginAs($user);
        $this->assertApiError($this->getJson('/players'), 403, 'forbidden');
    }
}
