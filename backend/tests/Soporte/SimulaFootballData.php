<?php

namespace Tests\Soporte;

/** Respuestas recortadas con la misma forma que football-data.org v4. */
trait SimulaFootballData
{
    protected function respuestaLigas(): array
    {
        return [
            'count' => 2,
            'filters' => [],
            'competitions' => [
                $this->respuestaLiga(),
                [
                    'id' => 2152,
                    'area' => ['id' => 2220, 'name' => 'South America', 'code' => 'SAM', 'flag' => null],
                    'name' => 'Copa Libertadores',
                    'code' => 'CLI',
                    'type' => 'CUP',
                    'emblem' => 'https://crests.football-data.org/CLI.svg',
                    'plan' => 'TIER_FOUR',
                    'currentSeason' => ['id' => 2400, 'startDate' => '2026-02-04', 'endDate' => '2026-11-28', 'currentMatchday' => 8],
                    'numberOfAvailableSeasons' => 3,
                    'lastUpdated' => '2026-09-01T10:00:00Z',
                ],
            ],
        ];
    }

    protected function respuestaLiga(): array
    {
        return [
            'id' => 2021,
            'area' => ['id' => 2072, 'name' => 'England', 'code' => 'ENG', 'flag' => 'https://crests.football-data.org/770.svg'],
            'name' => 'Premier League',
            'code' => 'PL',
            'type' => 'LEAGUE',
            'emblem' => 'https://crests.football-data.org/PL.png',
            'plan' => 'TIER_ONE',
            'currentSeason' => ['id' => 2500, 'startDate' => '2026-08-15', 'endDate' => '2027-05-23', 'currentMatchday' => 7],
            'lastUpdated' => '2026-10-01T08:00:00Z',
        ];
    }

    /** @param  array<int, array<string, mixed>>|null  $equipos */
    protected function respuestaPlanteles(?array $equipos = null): array
    {
        return [
            'count' => 2,
            'filters' => ['season' => '2026'],
            'competition' => [
                'id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE',
                'emblem' => 'https://crests.football-data.org/PL.png',
            ],
            'season' => ['id' => 2500, 'startDate' => '2026-08-15', 'endDate' => '2027-05-23', 'currentMatchday' => 7],
            'teams' => $equipos ?? [
                [
                    'area' => ['id' => 2072, 'name' => 'England', 'code' => 'ENG'],
                    'id' => 57,
                    'name' => 'Arsenal FC',
                    'shortName' => 'Arsenal',
                    'tla' => 'ARS',
                    'crest' => 'https://crests.football-data.org/57.png',
                    'address' => '75 Drayton Park London N5 1BU',
                    'website' => 'http://www.arsenal.com',
                    'founded' => 1886,
                    'clubColors' => 'Red / White',
                    'venue' => 'Emirates Stadium',
                    'coach' => ['id' => 11619, 'name' => 'Mikel Arteta'],
                    'squad' => [
                        ['id' => 7778, 'name' => 'David Raya', 'position' => 'Goalkeeper', 'dateOfBirth' => '1995-09-15', 'nationality' => 'Spain', 'shirtNumber' => 22],
                        ['id' => 4832, 'name' => 'Bukayo Saka', 'position' => 'Offence', 'dateOfBirth' => '2001-09-05', 'nationality' => 'England'],
                    ],
                    'lastUpdated' => '2026-09-30T10:00:00Z',
                ],
                [
                    'area' => ['id' => 2072, 'name' => 'England', 'code' => 'ENG'],
                    'id' => 65,
                    'name' => 'Manchester City FC',
                    'shortName' => 'Man City',
                    'tla' => 'MCI',
                    'crest' => 'https://crests.football-data.org/65.png',
                    'founded' => 1880,
                    'venue' => 'Etihad Stadium',
                    'coach' => null,
                    'squad' => [
                        ['id' => 3754, 'name' => 'Erling Haaland', 'position' => 'Centre-Forward', 'dateOfBirth' => '2000-07-21', 'nationality' => 'Norway', 'shirtNumber' => 9],
                    ],
                    'lastUpdated' => '2026-09-30T10:00:00Z',
                ],
            ],
        ];
    }
}
