<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccess();
        $this->admin = $this->actingAsRole('admin');
    }

    #[Test]
    public function an_admin_creates_a_user_with_roles_and_a_hashed_password(): void
    {
        $this->postJson('/users', [
            'name' => 'Pedro Nuevo',
            'email' => 'Pedro@IncaRail.test',
            'password' => 'secret123',
            'roles' => ['analista'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'pedro@incarail.test')
            ->assertJsonPath('data.roles', ['analista'])
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.password');

        $user = User::where('email', 'pedro@incarail.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertTrue($user->can('competitions.import'));
    }

    #[Test]
    public function user_payloads_are_validated(): void
    {
        $this->assertApiError($this->postJson('/users', [
            'name' => '',
            'email' => $this->admin->email,
            'password' => 'short',
            'roles' => ['root'],
        ]), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['name', 'email', 'password', 'roles.0']]]])
            ->assertJsonPath('error.details.fields.email.0', 'El correo ya está en uso.');
    }

    #[Test]
    public function an_admin_edits_a_user_and_changes_its_roles(): void
    {
        $user = $this->userWithRole('consulta');

        $this->putJson("/users/{$user->id}", ['name' => 'Renombrado', 'roles' => ['analista', 'consulta']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renombrado')
            ->assertJsonPath('data.roles', ['analista', 'consulta']);

        $this->putJson("/users/{$user->id}", ['email' => $user->email])->assertOk();
    }

    #[Test]
    public function the_user_list_can_be_searched(): void
    {
        $this->userWithRole('consulta', ['name' => 'Rosa Buscable']);

        $this->getJson('/users?search=buscable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rosa Buscable');
    }

    #[Test]
    public function deactivating_a_user_closes_its_sessions_and_blocks_new_logins(): void
    {
        $user = $this->userWithRole('consulta');
        $token = $this->loginAs($user);
        $this->withToken($this->loginAs($this->admin));

        $this->patchJson("/users/{$user->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->forgetAuthenticatedUser();
        $this->assertApiError($this->withToken($token)->getJson('/auth/me'), 401, 'unauthenticated');
        $this->assertApiError($this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password']), 403, 'account_disabled');

        $this->withToken($this->loginAs($this->admin));
        $this->patchJson("/users/{$user->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    #[Test]
    public function an_admin_cannot_lock_everybody_out(): void
    {
        $this->assertApiError($this->patchJson("/users/{$this->admin->id}/deactivate"), 409, 'conflict');
        $this->assertApiError($this->putJson("/users/{$this->admin->id}", ['roles' => ['consulta']]), 409, 'conflict');
    }

    #[Test]
    public function a_missing_user_answers_404(): void
    {
        $this->assertApiError($this->getJson('/users/99999'), 404, 'not_found')
            ->assertJsonPath('error.message', 'El usuario solicitado no existe.');
    }
}
