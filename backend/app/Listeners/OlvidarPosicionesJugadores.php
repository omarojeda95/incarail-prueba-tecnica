<?php

namespace App\Listeners;

use App\Aplicacion\Futbol\Consultas\ConsultasJugadores;
use App\Dominio\Futbol\Eventos\LigaImportada;
use Illuminate\Support\Facades\Cache;

class OlvidarPosicionesJugadores
{
    public function handle(LigaImportada $evento): void
    {
        Cache::forget(ConsultasJugadores::CLAVE_CACHE_POSICIONES);
    }
}
