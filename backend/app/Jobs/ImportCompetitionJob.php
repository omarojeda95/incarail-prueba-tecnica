<?php

namespace App\Jobs;

use App\Application\Football\ImportCompetition;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Imports a competition in the background. When the request budget is spent
 * the job is released back to the queue exactly until a slot frees up, so the
 * worker never exceeds the 10 requests per minute and nothing is lost.
 */
class ImportCompetitionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Unexpected errors allowed before giving up (releases do not count). */
    public int $maxExceptions = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int|string $competition, public readonly string $trigger = 'queue') {}

    public function uniqueId(): string
    {
        return strtoupper((string) $this->competition);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(ImportCompetition $import): void
    {
        try {
            $import->handle($this->competition, $this->trigger);
        } catch (FootballDataRateLimited $e) {
            $this->release($e->retryAfter + random_int(1, 5));
        } catch (FootballDataUnavailable) {
            $this->release(60);
        }
    }
}
