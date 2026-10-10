<?php

namespace Tests\Feature\BaseDeDatos;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MantenimientoTest extends TestCase
{
    #[Test]
    public function los_tokens_revocados_y_vencidos_se_purgan_cada_dia(): void
    {
        $purga = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $evento) => str_contains($evento->command ?? '', 'passport:purge'));

        $this->assertNotNull($purga, 'passport:purge no está programado.');
        $this->assertSame('0 4 * * *', $purga->expression);
    }
}
