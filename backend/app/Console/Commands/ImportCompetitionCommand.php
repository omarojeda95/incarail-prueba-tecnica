<?php

namespace App\Console\Commands;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Exceptions\FootballDataException;
use App\Jobs\ImportCompetitionJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('football:import {competition* : Ids or codes, e.g. PL 2014 SA} {--queue : Queue the imports instead of running them now}')]
#[Description('Import competitions (teams and squads) from football-data.org')]
class ImportCompetitionCommand extends Command
{
    public function handle(ImportCompetition $import): int
    {
        $status = self::SUCCESS;

        foreach ($this->argument('competition') as $competition) {
            if ($this->option('queue')) {
                ImportCompetitionJob::dispatch($competition, 'console');
                $this->components->info("{$competition}: queued.");

                continue;
            }

            try {
                $imported = $import->handle($competition, 'console');
                $this->components->info("{$competition}: {$imported->name} imported.");
            } catch (FootballDataException $e) {
                $this->components->error("{$competition}: {$e->getMessage()}");
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
