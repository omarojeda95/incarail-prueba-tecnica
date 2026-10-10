<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccess();
    }

    #[Test]
    public function login_returns_a_bearer_token_the_user_and_its_casl_rules(): void
    {
        $user = $this->userWithRole('consulta', ['email' => 'carla@incarail.test']);

        $this->postJson('/auth/login', ['email' => 'CARLA@incarail.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.roles', ['consulta'])
            ->assertJsonStructure(['access_token', 'expires_at'])
            ->assertJsonPath('abilities', [
                ['action' => 'read', 'subject' => 'Competition'],
                ['action' => 'read', 'subject' => 'Player'],
                ['action' => 'read', 'subject' => 'Team'],
            ]);
    }

    #[Test]
    public function passwords_are_stored_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret-123']);

        $this->assertNotSame('secret-123', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('secret-123', $user->getRawOriginal('password')));
    }

    #[Test]
    public function wrong_credentials_are_rejected_with_401(): void
    {
        $user = $this->userWithRole('consulta');

        $this->assertApiError($this->postJson('/auth/login', ['email' => $user->email, 'password' => 'nope']), 401, 'invalid_credentials');
        $this->assertApiError($this->postJson('/auth/login', ['email' => 'ghost@incarail.test', 'password' => 'nope']), 401, 'invalid_credentials');
    }

    #[Test]
    public function invalid_login_payload_is_rejected_with_422_and_field_errors(): void
    {
        $this->assertApiError($this->postJson('/auth/login', ['email' => 'not-an-email']), 422, 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['email', 'password']]]]);
    }

    #[Test]
    public function a_deactivated_user_cannot_log_in(): void
    {
        $user = $this->userWithRole('consulta', ['is_active' => false]);

        $this->assertApiError($this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password']), 403, 'account_disabled');
    }

    #[Test]
    public function every_route_but_login_requires_a_token(): void
    {
        foreach (['/auth/me', '/competitions', '/competitions/PL', '/team', '/team/1', '/players', '/users', '/roles'] as $uri) {
            $this->assertApiError($this->getJson($uri), 401, 'unauthenticated');
        }
    }

    #[Test]
    public function an_invalid_token_is_rejected(): void
    {
        $this->assertApiError($this->withToken('not-a-real-token')->getJson('/auth/me'), 401, 'unauthenticated');
    }

    #[Test]
    public function logout_revokes_the_token(): void
    {
        $user = $this->userWithRole('consulta');
        $token = $this->loginAs($user);

        $this->getJson('/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/auth/logout')->assertNoContent();

        $this->forgetAuthenticatedUser();
        $this->assertApiError($this->withToken($token)->getJson('/auth/me'), 401, 'unauthenticated');
    }

    #[Test]
    public function a_user_deactivated_while_logged_in_loses_access_at_once(): void
    {
        $user = $this->userWithRole('consulta');
        $this->loginAs($user);

        $user->forceFill(['is_active' => false])->save();
        $this->forgetAuthenticatedUser();

        $this->assertApiError($this->getJson('/auth/me'), 403, 'account_disabled');
    }

    #[Test]
    public function login_attempts_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => 'brute@incarail.test', 'password' => 'guess'.$i]);
        }

        $this->assertApiError($this->postJson('/auth/login', ['email' => 'brute@incarail.test', 'password' => 'again']), 429, 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}
