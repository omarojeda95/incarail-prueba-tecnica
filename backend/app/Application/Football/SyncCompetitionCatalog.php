<?php

namespace App\Application\Football;

use App\Domain\Football\Data\CompetitionData;
use App\Domain\Football\Ports\FootballDataProvider;
use App\Models\Competition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Command: refresh the list of competitions (one football-data request). */
final class SyncCompetitionCatalog
{
    private const SYNCED_AT_KEY = 'football:catalog:synced-at';

    public function __construct(private readonly FootballDataProvider $provider) {}

    public function handle(): int
    {
        $rows = array_map(CompetitionAttributes::from(...), $this->provider->competitions());

        if ($rows !== []) {
            // teams_synced_at is deliberately not touched: it belongs to the import.
            Competition::query()->upsert($rows, ['id'], array_keys(array_diff_key($rows[0], ['id' => true])));
        }

        Cache::forever(self::SYNCED_AT_KEY, now()->getTimestamp());

        return count($rows);
    }

    public function lastSyncedAt(): ?Carbon
    {
        $timestamp = Cache::get(self::SYNCED_AT_KEY);

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public function isStale(): bool
    {
        $syncedAt = $this->lastSyncedAt();

        return $syncedAt === null
            || $syncedAt->lt(now()->subMinutes(config('football.freshness.competitions_minutes')))
            || ! Competition::query()->exists();
    }
}
