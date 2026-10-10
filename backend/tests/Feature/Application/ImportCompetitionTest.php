<?php

namespace Tests\Feature\Application;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Events\CompetitionImported;
use App\Domain\Football\Ports\FootballDataProvider;
use App\Models\Competition;
use App\Models\CompetitionImport;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fakes\InMemoryFootballDataProvider as Fake;
use Tests\TestCase;

class ImportCompetitionTest extends TestCase
{
    use RefreshDatabase;

    private Fake $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new Fake;
        $this->app->instance(FootballDataProvider::class, $this->provider);
    }

    #[Test]
    public function it_stores_the_competition_its_teams_and_their_players(): void
    {
        $this->provider->withSquads($this->premierLeague());

        $competition = $this->app->make(ImportCompetition::class)->handle('PL');

        $this->assertSame(2021, $competition->id);
        $this->assertTrue($competition->isImported());
        $this->assertSame(2, Team::count());
        $this->assertSame(3, Player::count());
        $this->assertEqualsCanonicalizing([57, 65], $competition->teams()->pluck('teams.id')->all());
        $this->assertDatabaseHas('players', ['id' => 1, 'name' => 'David Raya', 'position' => 'Goalkeeper', 'shirt_number' => 22, 'team_id' => 57]);
    }

    #[Test]
    public function importing_the_same_competition_twice_does_not_duplicate_records(): void
    {
        $this->provider->withSquads($this->premierLeague());
        $import = $this->app->make(ImportCompetition::class);

        $import->handle(2021);
        $import->handle('PL');

        $this->assertSame(1, Competition::count());
        $this->assertSame(2, Team::count());
        $this->assertSame(3, Player::count());
        $this->assertSame(2, Competition::find(2021)->teams()->count());
    }

    #[Test]
    public function a_new_import_updates_what_changed(): void
    {
        $this->provider->withSquads($this->premierLeague());
        $import = $this->app->make(ImportCompetition::class);
        $import->handle(2021);

        // Saka moves to Manchester City and gets a shirt number.
        $this->provider->withSquads(new CompetitionSquads(Fake::competition(), '2026', [
            Fake::team(57, 'Arsenal FC', [Fake::player(1, 'David Raya', 'Goalkeeper', 22)]),
            Fake::team(65, 'Manchester City FC', [Fake::player(2, 'Bukayo Saka', 'Offence', 7), Fake::player(3, 'Erling Haaland', 'Offence', 9)]),
        ]));
        $import->handle(2021);

        $this->assertDatabaseHas('players', ['id' => 2, 'team_id' => 65, 'shirt_number' => 7]);
        $this->assertSame(3, Player::count());
    }

    #[Test]
    public function teams_that_leave_the_competition_are_unlinked_and_the_season_is_updated(): void
    {
        $this->provider->withSquads($this->premierLeague());
        $import = $this->app->make(ImportCompetition::class);
        $import->handle('PL');

        // Next season: Manchester City is gone, Arsenal stays, Ipswich arrives.
        $this->provider->withSquads(new CompetitionSquads(Fake::competition(), '2027', [
            Fake::team(57, 'Arsenal FC', [Fake::player(1, 'David Raya', 'Goalkeeper', 22)]),
            Fake::team(349, 'Ipswich Town FC', [Fake::player(9, 'Leif Davis', 'Defence', 3)]),
        ]));
        $import->handle('PL');

        $links = Competition::findOrFail(2021)->teams()->orderBy('teams.id')->get();
        $this->assertSame([57, 349], $links->pluck('id')->all());
        $this->assertSame(['2027', '2027'], $links->pluck('pivot.season')->all());
        $this->assertTrue(Team::whereKey(65)->exists(), 'The team itself is kept, only the link is removed.');
    }

    #[Test]
    public function it_keeps_catalog_fields_the_squads_endpoint_does_not_send(): void
    {
        Competition::factory()->create(['id' => 2021, 'code' => 'PL', 'plan' => 'TIER_ONE', 'area_name' => 'England']);
        $partial = new CompetitionSquads(
            new CompetitionData(2021, 'Premier League', 'PL', 'LEAGUE', null, null, null, null, null, null, null, 7, null),
            '2026',
            [],
        );

        $this->provider->withSquads($partial);

        $this->app->make(ImportCompetition::class)->handle(2021);

        $this->assertDatabaseHas('competitions', ['id' => 2021, 'plan' => 'TIER_ONE', 'area_name' => 'England', 'current_matchday' => 7]);
    }

    #[Test]
    public function it_announces_the_import_with_a_domain_event(): void
    {
        Event::fake([CompetitionImported::class]);
        $this->provider->withSquads($this->premierLeague());

        $this->app->make(ImportCompetition::class)->handle('PL', trigger: 'queue');

        Event::assertDispatched(CompetitionImported::class, fn (CompetitionImported $event) => $event->competitionId === 2021
            && $event->teamsCount === 2
            && $event->playersCount === 3
            && $event->trigger === 'queue');
    }

    #[Test]
    public function the_import_is_recorded_in_the_audit_trail(): void
    {
        $this->provider->withSquads($this->premierLeague());

        $this->app->make(ImportCompetition::class)->handle('PL');

        $this->assertSame(1, CompetitionImport::where('competition_id', 2021)->where('players_count', 3)->count());
    }

    private function premierLeague(): CompetitionSquads
    {
        return new CompetitionSquads(Fake::competition(), '2026', [
            Fake::team(57, 'Arsenal FC', [Fake::player(1, 'David Raya', 'Goalkeeper', 22), Fake::player(2, 'Bukayo Saka', 'Offence')]),
            Fake::team(65, 'Manchester City FC', [Fake::player(3, 'Erling Haaland', 'Offence', 9)]),
        ]);
    }
}
