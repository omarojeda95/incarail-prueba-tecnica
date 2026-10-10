<?php

namespace App\Application\Football\Queries;

use App\Application\Football\SyncCompetitionCatalog;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query: the competitions list. Always answered from our database; the
 * catalog is refreshed from football-data first only when it is stale, and
 * if that refresh fails we still answer with what we have (flagged as stale).
 */
final class CompetitionCatalog
{
    public function __construct(private readonly SyncCompetitionCatalog $sync) {}

    /** @param  array{search?: string, area?: string, imported?: bool, available?: bool}  $filters */
    public function handle(array $filters = []): QueryResult
    {
        $stale = false;

        if ($this->sync->isStale()) {
            try {
                $this->sync->handle();
            } catch (FootballDataRateLimited|FootballDataUnavailable $e) {
                if (! Competition::query()->exists()) {
                    throw $e;
                }
                $stale = true;
            }
        }

        $competitions = Competition::query()
            ->withCount('teams')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('code', "%{$search}%")
                    ->orWhereLike('area_name', "%{$search}%")
            ))
            ->when($filters['area'] ?? null, fn (Builder $query, string $area) => $query->where('area_name', $area))
            ->when(isset($filters['imported']), fn (Builder $query) => $filters['imported']
                ? $query->whereNotNull('teams_synced_at')
                : $query->whereNull('teams_synced_at'))
            ->when(isset($filters['available']), fn (Builder $query) => $filters['available']
                ? $query->whereIn('plan', config('football.available_plans'))
                : $query->whereNotIn('plan', config('football.available_plans')))
            ->orderBy('area_name')
            ->orderBy('name')
            ->get();

        return new QueryResult($competitions, stale: $stale, syncedAt: $this->sync->lastSyncedAt());
    }
}
