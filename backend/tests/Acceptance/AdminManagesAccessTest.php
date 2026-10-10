<?php

namespace Tests\Acceptance;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/**
 * "As an administrator I create a role and a user with it; the new user only
 * sees what the role allows, and loses access the moment I deactivate them."
 */
class AdminManagesAccessTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    #[Test]
    public function the_admin_grants_and_revokes_access(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
        $this->seed(DatabaseSeeder::class);

        // 1. The admin creates a "scouting" role that can only read players.
        $admin = $this->loginAs('admin@incarail.test');
        $this->postJson('/roles', ['name' => 'scouting', 'permissions' => ['players.read']])->assertCreated();

        // 2. ...and a user with that role.
        $userId = $this->postJson('/users', [
            'name' => 'Sofía Scout',
            'email' => 'sofia@incarail.test',
            'password' => 'scout2026',
            'roles' => ['scouting'],
        ])->assertCreated()->json('data.id');

        // 3. Sofía logs in and receives exactly one CASL rule.
        $this->forgetAuthenticatedUser();
        $sofia = $this->postJson('/auth/login', ['email' => 'sofia@incarail.test', 'password' => 'scout2026'])
            ->assertOk()
            ->assertJsonPath('abilities', [['action' => 'read', 'subject' => 'Player']])
            ->json('access_token');

        $this->withToken($sofia)->getJson('/players')->assertOk();
        $this->assertApiError($this->withToken($sofia)->getJson('/team'), 403, 'forbidden');

        // 4. The admin deactivates her: her open session stops working.
        $this->forgetAuthenticatedUser();
        $this->withToken($admin)->patchJson("/users/{$userId}/deactivate")->assertOk();

        $this->forgetAuthenticatedUser();
        $this->assertApiError($this->withToken($sofia)->getJson('/players'), 401, 'unauthenticated');
    }
}
