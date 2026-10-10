<?php

namespace Tests\Acceptance;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFootballData;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/**
 * "As an analyst I log in, look for the Premier League, open it so its teams
 * and players are stored, browse them, and keep working when football-data
 * stops answering." Uses the real seeders and the documented demo user.
 */
class AnalystExploresALeagueTest extends TestCase
{
    use FakesFootballData, InteractsWithAccess, RefreshDatabase;

    #[Test]
    public function the_analyst_imports_a_league_and_browses_it_even_when_football_data_goes_down(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys');
        }
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
        config(['football.token' => 'test-token', 'football.base_url' => 'https://football.test/v4']);
        Http::fake([
            'football.test/v4/competitions' => Http::response($this->competitionsPayload()),
            'football.test/v4/competitions/2021/teams' => Http::response($this->competitionTeamsPayload()),
        ]);

        // 1. Log in with the documented demo user.
        $session = $this->postJson('/auth/login', ['email' => 'analista@incarail.test', 'password' => 'password'])->assertOk();
        $this->withToken($session->json('access_token'));
        $this->assertContains(['action' => 'import', 'subject' => 'Competition'], $session->json('abilities'));

        // 2. Find the league among the available ones.
        $this->getJson('/competitions?available=1&search=premier')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.imported', false);

        // 3. Open it: teams and players are stored.
        $this->getJson('/competitions/2021')->assertOk()->assertJsonCount(2, 'data.teams');

        // 4. Browse what was stored.
        $teamId = $this->getJson('/team?search=arsenal')->assertOk()->json('data.0.id');
        $this->getJson("/team/{$teamId}")->assertOk()->assertJsonCount(2, 'data.players');
        $this->getJson('/players?position=Goalkeeper')->assertOk()->assertJsonPath('data.0.name', 'David Raya');

        // 5. football-data goes down: everything already stored keeps working.
        Http::fake(fn () => throw new ConnectionException('down'));
        $this->getJson('/competitions/2021')->assertOk()->assertJsonPath('meta.source', 'database');
        $this->getJson('/competitions')->assertOk();
        $this->getJson('/players')->assertOk()->assertJsonPath('meta.total', 3);

        // 6. The analyst may not manage users.
        $this->assertApiError($this->getJson('/users'), 403, 'forbidden');

        // 7. Log out: the token is no longer valid.
        $this->postJson('/auth/logout')->assertNoContent();
        $this->forgetAuthenticatedUser();
        $this->assertApiError($this->getJson('/team'), 401, 'unauthenticated');
    }
}
