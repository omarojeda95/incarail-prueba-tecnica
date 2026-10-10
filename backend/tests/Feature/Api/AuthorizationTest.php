<?php

namespace Tests\Feature\Api;

use App\Models\Competition;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/** What each seeded role may do is decided by the API, route by route. */
class AuthorizationTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccess();
        Queue::fake();

        $competition = Competition::factory()->imported()->create(['id' => 2021, 'code' => 'PL']);
        $team = Team::factory()->create(['id' => 57]);
        $competition->teams()->attach($team);
        Player::factory()->for($team)->create(['id' => 7]);
        cache()->forever('football:catalog:synced-at', now()->getTimestamp());
    }

    /** @return array<string, array{string, string, string, array<string, int>}> */
    public static function matrix(): array
    {
        return [
            'list competitions' => ['GET', '/competitions', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'show competition' => ['GET', '/competitions/PL', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'queue an import' => ['POST', '/competitions/PL/import', '', ['admin' => 202, 'analista' => 202, 'consulta' => 403]],
            'list teams' => ['GET', '/team', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'show team' => ['GET', '/team/57', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'list players' => ['GET', '/players', '', ['admin' => 200, 'analista' => 200, 'consulta' => 200]],
            'list users' => ['GET', '/users', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
            'create user' => ['POST', '/users', 'user', ['admin' => 201, 'analista' => 403, 'consulta' => 403]],
            'list roles' => ['GET', '/roles', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
            'create role' => ['POST', '/roles', 'role', ['admin' => 201, 'analista' => 403, 'consulta' => 403]],
            'list permissions' => ['GET', '/permissions', '', ['admin' => 200, 'analista' => 403, 'consulta' => 403]],
        ];
    }

    /** @param  array<string, int>  $expected */
    #[Test]
    #[DataProvider('matrix')]
    public function each_role_gets_exactly_its_permissions(string $method, string $uri, string $payload, array $expected): void
    {
        foreach ($expected as $role => $status) {
            $this->forgetAuthenticatedUser();
            $this->actingAsRole($role);

            $body = match ($payload) {
                'user' => ['name' => 'Nuevo', 'email' => "nuevo-{$role}@incarail.test", 'password' => 'secret123', 'roles' => ['consulta']],
                'role' => ['name' => "rol-{$role}", 'permissions' => ['teams.read']],
                default => [],
            };

            $response = $this->json($method, $uri, $body);

            $this->assertSame($status, $response->status(), "{$role} {$method} {$uri}: ".$response->getContent());
            if ($status === 403) {
                $this->assertApiError($response, 403, 'forbidden');
            }
        }
    }
}
