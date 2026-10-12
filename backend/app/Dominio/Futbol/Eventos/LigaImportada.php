<?php

namespace App\Dominio\Futbol\Eventos;

final class LigaImportada
{
    public function __construct(
        public readonly int $ligaId,
        public readonly int $cantidadEquipos,
        public readonly int $cantidadJugadores,
        public readonly string $origen,
    ) {}
}
