<?php

namespace App\Console\Commands;

use App\Jobs\ImportarLigaJob;
use App\Models\Liga;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('futbol:refrescar-vencidas')]
#[Description('Encola la actualización de cada liga importada cuyos datos estén vencidos')]
class RefrescarLigasVencidasCommand extends Command
{
    public function handle(): int
    {
        $vencidas = Liga::query()
            ->importadas()
            ->where('equipos_sincronizados_en', '<', now()->subMinutes(config('futbol.vigencia.detalle_liga_minutos')))
            ->pluck('id');

        // Los jobs se autorregulan con el presupuesto de peticiones compartido.
        $vencidas->each(fn (int $id) => ImportarLigaJob::dispatch($id, 'programada'));

        $this->components->info("{$vencidas->count()} ligas encoladas para actualizar.");

        return self::SUCCESS;
    }
}
