<?php

namespace App\Domain\Football\Exceptions;

/** The request budget for the provider is exhausted (our own limiter or theirs). */
final class FootballDataRateLimited extends FootballDataException
{
    public function __construct(public readonly int $retryAfter, public readonly bool $queued = false)
    {
        parent::__construct("football-data request limit reached, retry in {$retryAfter}s.");
    }
}
