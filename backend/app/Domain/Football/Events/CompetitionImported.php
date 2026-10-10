<?php

namespace App\Domain\Football\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class CompetitionImported
{
    use Dispatchable;

    public function __construct(
        public readonly int $competitionId,
        public readonly int $teamsCount,
        public readonly int $playersCount,
        public readonly string $trigger,
    ) {}
}
