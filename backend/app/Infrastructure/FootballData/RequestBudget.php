<?php

namespace App\Infrastructure\FootballData;

use App\Domain\Football\Exceptions\FootballDataRateLimited;

/** How many requests we may still send to football-data right now. */
interface RequestBudget
{
    /**
     * Reserve one request slot.
     *
     * @throws FootballDataRateLimited when no slot is free
     */
    public function acquire(): void;

    public function remaining(): int;

    /** Seconds until the next slot frees up (0 when one is available). */
    public function retryAfter(): int;

    /** Block every request for a while (the provider told us we ran out). */
    public function exhaustFor(int $seconds): void;
}
