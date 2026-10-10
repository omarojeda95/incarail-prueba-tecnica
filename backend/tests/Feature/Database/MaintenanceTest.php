<?php

namespace Tests\Feature\Database;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    #[Test]
    public function revoked_and_expired_access_tokens_are_purged_every_day(): void
    {
        $purge = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command ?? '', 'passport:purge'));

        $this->assertNotNull($purge, 'passport:purge is not scheduled.');
        $this->assertSame('0 4 * * *', $purge->expression);
    }
}
