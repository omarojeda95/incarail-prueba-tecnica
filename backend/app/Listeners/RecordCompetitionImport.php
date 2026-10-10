<?php

namespace App\Listeners;

use App\Domain\Football\Events\CompetitionImported;
use App\Models\CompetitionImport;

/** Keeps the audit trail of imports (shown in the SPA as "last import"). */
class RecordCompetitionImport
{
    public function handle(CompetitionImported $event): void
    {
        CompetitionImport::query()->create([
            'competition_id' => $event->competitionId,
            'teams_count' => $event->teamsCount,
            'players_count' => $event->playersCount,
            'trigger' => $event->trigger,
            'imported_at' => now(),
        ]);
    }
}
