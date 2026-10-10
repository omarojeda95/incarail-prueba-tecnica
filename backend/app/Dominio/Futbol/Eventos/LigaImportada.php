<?php

namespace App\Dominio\Futbol\Eventos;

use Illuminate\Foundation\Events\Dispatchable;

final class LigaImportada
{
    use Dispatchable;

    public function __construct(
        public readonly int $ligaId,
        public readonly int $cantidadEquipos,
        public readonly int $cantidadJugadores,
        public readonly string $origen,
    ) {}
}
