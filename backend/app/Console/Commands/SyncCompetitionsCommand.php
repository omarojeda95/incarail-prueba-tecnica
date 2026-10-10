<?php

namespace App\Console\Commands;

use App\Application\Football\SyncCompetitionCatalog;
use App\Domain\Football\Exceptions\FootballDataException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('football:sync-competitions')]
#[Description('Refresh the competitions catalog from football-data.org (1 request)')]
class SyncCompetitionsCommand extends Command
{
    public function handle(SyncCompetitionCatalog $sync): int
    {
        try {
            $this->components->info("{$sync->handle()} competitions synced.");

            return self::SUCCESS;
        } catch (FootballDataException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
