<?php

namespace App\Listeners;

use App\Aplicacion\Futbol\Consultas\ConsultasJugadores;
use App\Dominio\Futbol\Eventos\LigaImportada;
use Illuminate\Support\Facades\Cache;

/** Una importación nueva puede traer posiciones nuevas: se descarta la lista en caché. */
class OlvidarPosicionesJugadores
{
    public function handle(LigaImportada $evento): void
    {
        Cache::forget(ConsultasJugadores::CLAVE_CACHE_POSICIONES);
    }
}
