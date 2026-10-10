<?php

namespace App\Application\Football\Queries;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Exceptions\CompetitionNotAvailable;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Jobs\ImportCompetitionJob;
use App\Models\Competition;

/**
 * Query: one competition with its teams. Read-through cache over our database:
 *  - imported and fresh             -> answered locally, no external request
 *  - missing or stale               -> imported now (1 football-data request)
 *  - no budget / provider down      -> stale local copy if we have one, and the
 *                                      import is queued to run when budget frees
 */
final class CompetitionDetail
{
    public function __construct(private readonly ImportCompetition $import) {}

    public function handle(string $reference): QueryResult
    {
        $local = Competition::query()->reference($reference)->first();

        if ($local?->isImported() && ! $local->isStale()) {
            return $this->result($local, stale: false, source: 'database');
        }

        if ($local?->plan !== null && ! $local->isImportable()) {
            return $local->isImported()
                ? $this->result($local, stale: true, source: 'database')
                : throw new CompetitionNotAvailable("Competition {$local->code} is not included in the football-data plan.");
        }

        try {
            return $this->result($this->import->handle($local?->id ?? $reference), stale: false, source: 'football-data');
        } catch (FootballDataRateLimited $e) {
            ImportCompetitionJob::dispatch($local?->id ?? strtoupper($reference));

            if ($local?->isImported()) {
                return $this->result($local, stale: true, source: 'database', retryAfter: $e->retryAfter);
            }

            throw new FootballDataRateLimited($e->retryAfter, queued: true);
        } catch (FootballDataUnavailable $e) {
            if ($local?->isImported()) {
                return $this->result($local, stale: true, source: 'database');
            }

            throw $e;
        }
    }

    private function result(Competition $competition, bool $stale, string $source, ?int $retryAfter = null): QueryResult
    {
        $competition->load([
            'teams' => fn ($query) => $query->withCount('players')->orderBy('name'),
            'latestImport',
        ]);

        return new QueryResult($competition, $stale, $competition->teams_synced_at, $source, $retryAfter);
    }
}
