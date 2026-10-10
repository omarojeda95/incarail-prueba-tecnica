<?php

namespace App\Listeners;

use App\Application\Football\Queries\PlayerQueries;
use App\Domain\Football\Events\CompetitionImported;
use Illuminate\Support\Facades\Cache;

/** A new import may bring new positions: drop the cached filter list. */
class ForgetPlayerPositions
{
    public function handle(CompetitionImported $event): void
    {
        Cache::forget(PlayerQueries::POSITIONS_CACHE_KEY);
    }
}
