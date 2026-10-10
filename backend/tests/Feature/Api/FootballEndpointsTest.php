<?php

namespace Tests\Feature\Api;

use App\Infrastructure\FootballData\RequestBudget;
use App\Jobs\ImportCompetitionJob;
use App\Models\Competition;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\FakesFootballData;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class FootballEndpointsTest extends TestCase
{
    use FakesFootballData, InteractsWithAccess, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAccess();
        config(['football.token' => 'test-token', 'football.base_url' => 'https://football.test/v4']);
        Queue::fake();
        $this->actingAsRole('consulta');
    }

    #[Test]
    public function competitions_are_synced_once_and_then_served_from_the_database(): void
    {
        Http::fake(['football.test/v4/competitions' => Http::response($this->competitionsPayload())]);

        $this->getJson('/competitions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.stale', false)
            ->assertJsonFragment(['code' => 'PL', 'available' => true, 'imported' => false]);

        $this->getJson('/competitions?search=libertadores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.available', false);

        Http::assertSentCount(1);
        $this->assertSame(2, Competition::count());
    }

    #[Test]
    public function the_competitions_list_survives_football_data_being_down(): void
    {
        Competition::factory()->create(['code' => 'PL']);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->getJson('/competitions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.stale', true);
    }

    #[Test]
    public function without_local_data_and_with_football_data_down_the_list_answers_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertApiError($this->getJson('/competitions'), 503, 'football_data_unavailable');
    }

    #[Test]
    public function an_upstream_server_error_answers_502(): void
    {
        Http::fake(['*' => Http::response('Bad gateway', 500)]);

        $this->assertApiError($this->getJson('/competitions'), 502, 'football_data_error');
    }

    #[Test]
    public function showing_a_competition_imports_its_teams_and_players_once(): void
    {
        Http::fake(['football.test/v4/competitions/PL/teams' => Http::response($this->competitionTeamsPayload())]);

        $this->getJson('/competitions/PL')
            ->assertOk()
            ->assertJsonPath('data.id', 2021)
            ->assertJsonPath('data.imported', true)
            ->assertJsonCount(2, 'data.teams')
            ->assertJsonPath('data.teams.0.name', 'Arsenal FC')
            ->assertJsonPath('data.teams.0.players_count', 2)
            ->assertJsonPath('data.last_import.players_count', 3)
            ->assertJsonPath('meta.source', 'football-data');

        $this->getJson('/competitions/2021')
            ->assertOk()
            ->assertJsonPath('meta.source', 'database');

        Http::assertSentCount(1);
        $this->assertSame(2, Team::count());
        $this->assertSame(3, Player::count());
    }

    #[Test]
    public function an_unknown_competition_answers_404(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The resource you are looking for does not exist.', 'errorCode' => 404], 404)]);

        $this->assertApiError($this->getJson('/competitions/9999'), 404, 'competition_not_found');
    }

    #[Test]
    public function a_competition_outside_our_plan_is_refused_without_spending_a_request(): void
    {
        Http::fake();
        Competition::factory()->create(['id' => 2152, 'code' => 'CLI', 'plan' => 'TIER_FOUR']);

        $this->assertApiError($this->getJson('/competitions/CLI'), 403, 'competition_not_in_plan');
        Http::assertNothingSent();
    }

    #[Test]
    public function the_eleventh_import_in_a_minute_is_not_sent_and_gets_queued_with_429(): void
    {
        Http::fake(['*' => Http::response($this->competitionTeamsPayload())]);

        // Ten different competitions consume the whole budget...
        for ($id = 1; $id <= 10; $id++) {
            $this->getJson("/competitions/{$id}")->assertOk();
            Competition::query()->whereKey(2021)->update(['teams_synced_at' => null]);
        }

        // ...the eleventh never reaches football-data: queued and answered 429.
        $this->assertApiError($this->getJson('/competitions/SA'), 429, 'football_data_rate_limited')
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.details.queued', true);

        Http::assertSentCount(10);
        Queue::assertPushed(ImportCompetitionJob::class, fn (ImportCompetitionJob $job) => $job->competition === 'SA');
    }

    #[Test]
    public function with_no_budget_left_a_stale_competition_is_still_served_from_the_database(): void
    {
        Http::fake();
        $competition = Competition::factory()->create(['id' => 2021, 'code' => 'PL', 'teams_synced_at' => now()->subDays(3)]);
        $competition->teams()->attach(Team::factory()->create());
        $this->app->make(RequestBudget::class)->exhaustFor(40);

        $this->getJson('/competitions/PL')
            ->assertOk()
            ->assertJsonPath('meta.stale', true)
            ->assertJsonPath('meta.retry_after', 40)
            ->assertJsonCount(1, 'data.teams');

        Http::assertNothingSent();
        Queue::assertPushed(ImportCompetitionJob::class);
    }

    #[Test]
    public function stored_teams_are_listed_searched_and_paginated(): void
    {
        $competition = Competition::factory()->create();
        Team::factory()->count(3)->create();
        $arsenal = Team::factory()->create(['name' => 'Arsenal FC']);
        $competition->teams()->attach($arsenal);

        $this->getJson('/team?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4);

        $this->getJson('/team?search=arsenal')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/team?competition_id={$competition->id}")->assertOk()->assertJsonPath('data.0.name', 'Arsenal FC');
        $this->assertApiError($this->getJson('/team?per_page=1000'), 422, 'validation_failed');
    }

    #[Test]
    public function a_team_detail_includes_its_players_and_competitions(): void
    {
        $team = Team::factory()->create(['name' => 'Arsenal FC']);
        Player::factory()->for($team)->create(['name' => 'David Raya', 'position' => 'Goalkeeper', 'shirt_number' => 22]);
        $team->competitions()->attach(Competition::factory()->create(['code' => 'PL']), ['season' => '2026']);

        $this->getJson("/team/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Arsenal FC')
            ->assertJsonPath('data.players.0.name', 'David Raya')
            ->assertJsonPath('data.players.0.shirt_number', 22)
            ->assertJsonPath('data.competitions.0.code', 'PL');
    }

    #[Test]
    public function a_missing_team_or_player_answers_404_with_a_clear_message(): void
    {
        $this->assertApiError($this->getJson('/team/123456'), 404, 'not_found')
            ->assertJsonPath('error.message', 'El equipo solicitado no existe.');

        $this->assertApiError($this->getJson('/players/123456'), 404, 'not_found')
            ->assertJsonPath('error.message', 'El jugador solicitado no existe.');
    }

    #[Test]
    public function stored_players_can_be_filtered_and_sorted(): void
    {
        $team = Team::factory()->create();
        Player::factory()->for($team)->create(['name' => 'Zack Keeper', 'position' => 'Goalkeeper']);
        Player::factory()->for($team)->create(['name' => 'Adam Striker', 'position' => 'Offence']);
        Player::factory()->create(['name' => 'Other Keeper', 'position' => 'Goalkeeper']);

        $this->getJson('/players')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'Adam Striker')
            ->assertJsonPath('filters.positions', ['Goalkeeper', 'Offence']);

        $this->getJson("/players?position=Goalkeeper&team_id={$team->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Zack Keeper');

        $this->getJson('/players?sort=name&direction=desc')->assertOk()->assertJsonPath('data.0.name', 'Zack Keeper');
        $this->assertApiError($this->getJson('/players?sort=password'), 422, 'validation_failed');
    }

    #[Test]
    public function stored_data_is_served_even_without_a_football_data_token(): void
    {
        config(['football.token' => null]);
        Http::fake();
        Player::factory()->create();

        $this->getJson('/team')->assertOk();
        $this->getJson('/players')->assertOk()->assertJsonCount(1, 'data');
        Http::assertNothingSent();
    }
}
