<?php

namespace App\Domain\Football\Data;

/** A competition with the teams (and their squads) of a season: what a league import needs. */
final readonly class CompetitionSquads
{
    /**
     * @param  list<TeamData>  $teams
     */
    public function __construct(
        public CompetitionData $competition,
        public ?string $season,
        public array $teams,
    ) {}

    public function playersCount(): int
    {
        return array_sum(array_map(fn (TeamData $team) => count($team->squad), $this->teams));
    }
}
