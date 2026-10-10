<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\PassportClientSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Testing\TestResponse;

/** Seeds the ACL and issues real Passport tokens, as the SPA would get them. */
trait InteractsWithAccess
{
    protected function setUpAccess(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }

        $this->seed([RolesAndPermissionsSeeder::class, PassportClientSeeder::class]);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        return tap(User::factory()->create($attributes), fn (User $user) => $user->assignRole($role));
    }

    /** Login through the API and remember the token for the next calls. */
    protected function loginAs(User|string $user, string $password = 'password'): string
    {
        $email = $user instanceof User ? $user->email : $user;

        $token = $this->postJson('/auth/login', ['email' => $email, 'password' => $password])
            ->assertOk()
            ->json('access_token');

        $this->withToken($token);

        return $token;
    }

    /** Login as a fresh user with the given role. */
    protected function actingAsRole(string $role): User
    {
        $user = $this->userWithRole($role);
        $this->loginAs($user);

        return $user;
    }

    /** Laravel keeps the resolved user between requests in a test: forget it. */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    protected function assertApiError(TestResponse $response, int $status, string $code): TestResponse
    {
        return $response
            ->assertStatus($status)
            ->assertJsonPath('error.status', $status)
            ->assertJsonPath('error.code', $code)
            ->assertJsonStructure(['error' => ['status', 'code', 'message']]);
    }
}
