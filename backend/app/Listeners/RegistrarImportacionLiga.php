<?php

namespace App\Listeners;

use App\Dominio\Futbol\Eventos\LigaImportada;
use App\Models\ImportacionLiga;

class RegistrarImportacionLiga
{
    public function handle(LigaImportada $evento): void
    {
        ImportacionLiga::query()->create([
            'liga_id' => $evento->ligaId,
            'cantidad_equipos' => $evento->cantidadEquipos,
            'cantidad_jugadores' => $evento->cantidadJugadores,
            'origen' => $evento->origen,
            'importado_en' => now(),
        ]);
    }
}
