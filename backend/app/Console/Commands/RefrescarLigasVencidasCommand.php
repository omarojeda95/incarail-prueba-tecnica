<?php

namespace App\Console\Commands;

use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use App\Models\Liga;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('futbol:refrescar-vencidas')]
#[Description('Encola la actualización de cada liga importada cuyos datos estén vencidos')]
class RefrescarLigasVencidasCommand extends Command
{
    public function handle(ColaDeImportaciones $cola): int
    {
        $vencidas = Liga::query()
            ->importadas()
            ->where('equipos_sincronizados_en', '<', now()->subMinutes(config('futbol.vigencia.detalle_liga_minutos')))
            ->pluck('id');

        $vencidas->each(fn (int $id) => $cola->encolar($id, 'programada'));

        $this->components->info("{$vencidas->count()} ligas encoladas para actualizar.");

        return self::SUCCESS;
    }
}
