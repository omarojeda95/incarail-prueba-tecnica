<?php

namespace App\Infrastructure\FootballData;

use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Data\CompetitionSquads;
use App\Domain\Football\Data\PlayerData;
use App\Domain\Football\Data\TeamData;
use App\Domain\Football\Exceptions\CompetitionNotAvailable;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Domain\Football\Exceptions\FootballResourceNotFound;
use App\Domain\Football\Ports\FootballDataProvider;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Adapter for football-data.org v4: the only class that talks HTTP to it.
 * Every request first takes a slot from the shared RequestBudget, and the
 * provider's own counters (headers / 429) feed back into that budget.
 */
final class FootballDataClient implements FootballDataProvider
{
    private const DEFAULT_RESET_SECONDS = 60;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly RequestBudget $budget,
        private readonly ?string $token,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function competitions(): array
    {
        $payload = $this->get('competitions');

        return array_map($this->mapCompetition(...), $payload['competitions'] ?? []);
    }

    public function competitionSquads(int|string $competition): CompetitionSquads
    {
        $payload = $this->get('competitions/'.rawurlencode((string) $competition).'/teams');
        $season = $payload['season'] ?? [];

        return new CompetitionSquads(
            competition: $this->mapCompetition([...$payload['competition'], 'currentSeason' => $season]),
            season: isset($season['startDate']) ? substr($season['startDate'], 0, 4) : null,
            teams: array_map($this->mapTeam(...), $payload['teams'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        if (blank($this->token)) {
            throw new FootballDataUnavailable('FOOTBALL_DATA_TOKEN is not configured.');
        }

        $this->budget->acquire();

        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->withHeaders(['X-Auth-Token' => $this->token])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->timeout)
                ->get($path);
        } catch (ConnectionException $e) {
            Log::warning('football-data unreachable', ['path' => $path, 'error' => $e->getMessage()]);

            throw new FootballDataUnavailable('football-data.org could not be reached.', connectionFailed: true, previous: $e);
        }

        $this->syncBudgetWith($response);

        return $this->payloadOf($response, $path);
    }

    /** @return array<string, mixed> */
    private function payloadOf(Response $response, string $path): array
    {
        $message = $response->json('message') ?? $response->reason();

        return match (true) {
            $response->successful() && is_array($response->json()) => $response->json(),
            $response->status() === 429 => throw new FootballDataRateLimited($this->resetSeconds($response)),
            $response->status() === 403 => throw new CompetitionNotAvailable($message),
            in_array($response->status(), [400, 404], true) => throw new FootballResourceNotFound($message),
            default => throw $this->unexpected($response, $path),
        };
    }

    private function unexpected(Response $response, string $path): FootballDataUnavailable
    {
        Log::error('football-data unexpected response', ['path' => $path, 'status' => $response->status()]);

        return new FootballDataUnavailable("football-data.org answered HTTP {$response->status()}.");
    }

    /** The provider is the source of truth for its own counter: align our budget with it. */
    private function syncBudgetWith(Response $response): void
    {
        $available = $response->header('X-Requests-Available-Minute');

        if ($response->status() === 429 || ($available !== '' && (int) $available <= 0)) {
            $this->budget->exhaustFor($this->resetSeconds($response));
        }
    }

    private function resetSeconds(Response $response): int
    {
        $reset = (int) $response->header('X-RequestCounter-Reset');

        return $reset > 0 ? $reset : self::DEFAULT_RESET_SECONDS;
    }

    /** @param  array<string, mixed>  $item */
    private function mapCompetition(array $item): CompetitionData
    {
        $season = $item['currentSeason'] ?? [];

        return new CompetitionData(
            id: (int) $item['id'],
            name: $item['name'],
            code: $item['code'] ?? null,
            type: $item['type'] ?? null,
            emblem: $item['emblem'] ?? null,
            plan: $item['plan'] ?? null,
            areaName: $item['area']['name'] ?? null,
            areaCode: $item['area']['code'] ?? null,
            areaFlag: $item['area']['flag'] ?? null,
            seasonStart: $this->date($season['startDate'] ?? null),
            seasonEnd: $this->date($season['endDate'] ?? null),
            currentMatchday: $season['currentMatchday'] ?? null,
            lastUpdated: $this->date($item['lastUpdated'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $item */
    private function mapTeam(array $item): TeamData
    {
        return new TeamData(
            id: (int) $item['id'],
            name: $item['name'],
            shortName: $item['shortName'] ?? null,
            tla: $item['tla'] ?? null,
            crest: $item['crest'] ?? null,
            address: $item['address'] ?? null,
            website: $item['website'] ?? null,
            founded: $item['founded'] ?? null,
            clubColors: $item['clubColors'] ?? null,
            venue: $item['venue'] ?? null,
            areaName: $item['area']['name'] ?? null,
            coachName: $item['coach']['name'] ?? null,
            lastUpdated: $this->date($item['lastUpdated'] ?? null),
            squad: array_map($this->mapPlayer(...), $item['squad'] ?? []),
        );
    }

    /** @param  array<string, mixed>  $item */
    private function mapPlayer(array $item): PlayerData
    {
        return new PlayerData(
            id: (int) $item['id'],
            name: $item['name'],
            position: $item['position'] ?? null,
            // Not every plan includes it in the squad: stored when present.
            shirtNumber: isset($item['shirtNumber']) ? (int) $item['shirtNumber'] : null,
            dateOfBirth: $this->date($item['dateOfBirth'] ?? null),
            nationality: $item['nationality'] ?? null,
        );
    }

    private function date(?string $value): ?DateTimeImmutable
    {
        return $value ? new DateTimeImmutable($value) : null;
    }
}
