<?php

namespace Tests\Fakes;

use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Data\PlayerData;
use App\Domain\Football\Data\TeamData;
use App\Domain\Football\Exceptions\FootballResourceNotFound;
use App\Domain\Football\Ports\FootballDataProvider;
use DateTimeImmutable;
use Throwable;

/** Second adapter for the provider port: lets the use cases run without HTTP. */
final class InMemoryFootballDataProvider implements FootballDataProvider
{
    /** @var array<int|string, CompetitionSquads> */
    private array $squads = [];

    /** @var list<CompetitionData> */
    private array $competitions = [];

    private ?Throwable $failure = null;

    public int $calls = 0;

    public function competitions(): array
    {
        $this->hit();

        return $this->competitions;
    }

    public function competitionSquads(int|string $competition): CompetitionSquads
    {
        $this->hit();

        return $this->squads[$competition] ?? throw new FootballResourceNotFound("Competition {$competition} not found.");
    }

    public function withCompetitions(CompetitionData ...$competitions): self
    {
        $this->competitions = $competitions;

        return $this;
    }

    public function withSquads(CompetitionSquads $squads): self
    {
        $this->squads[$squads->competition->id] = $squads;
        $this->squads[$squads->competition->code] = $squads;

        return $this;
    }

    public function failWith(?Throwable $failure): self
    {
        $this->failure = $failure;

        return $this;
    }

    private function hit(): void
    {
        $this->calls++;

        if ($this->failure) {
            throw $this->failure;
        }
    }

    public static function competition(int $id = 2021, string $code = 'PL', string $plan = 'TIER_ONE'): CompetitionData
    {
        return new CompetitionData(
            id: $id, name: "Competition {$code}", code: $code, type: 'LEAGUE', emblem: null, plan: $plan,
            areaName: 'England', areaCode: 'ENG', areaFlag: null,
            seasonStart: new DateTimeImmutable('2026-08-15'), seasonEnd: new DateTimeImmutable('2027-05-23'),
            currentMatchday: 7, lastUpdated: new DateTimeImmutable('2026-10-01'),
        );
    }

    /** @param  list<PlayerData>  $squad */
    public static function team(int $id, string $name, array $squad = []): TeamData
    {
        return new TeamData(
            id: $id, name: $name, shortName: $name, tla: strtoupper(substr($name, 0, 3)), crest: null,
            address: null, website: null, founded: 1900, clubColors: null, venue: null, areaName: 'England',
            coachName: null, lastUpdated: null, squad: $squad,
        );
    }

    public static function player(int $id, string $name, ?string $position = 'Midfield', ?int $shirt = null): PlayerData
    {
        return new PlayerData(
            id: $id, name: $name, position: $position, shirtNumber: $shirt,
            dateOfBirth: new DateTimeImmutable('2000-01-01'), nationality: 'England',
        );
    }
}
