<?php

namespace App\Console\Commands;

use App\Jobs\ImportCompetitionJob;
use App\Models\Competition;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('football:refresh-stale')]
#[Description('Queue a refresh for every imported competition whose data is stale')]
class RefreshStaleCompetitionsCommand extends Command
{
    public function handle(): int
    {
        $stale = Competition::query()
            ->imported()
            ->where('teams_synced_at', '<', now()->subMinutes(config('football.freshness.competition_detail_minutes')))
            ->pluck('id');

        // The jobs throttle themselves through the shared request budget.
        $stale->each(fn (int $id) => ImportCompetitionJob::dispatch($id, 'schedule'));

        $this->components->info("{$stale->count()} competitions queued for refresh.");

        return self::SUCCESS;
    }
}
