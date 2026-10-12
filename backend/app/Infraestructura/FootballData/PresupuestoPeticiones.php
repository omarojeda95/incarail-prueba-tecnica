<?php

namespace App\Infraestructura\FootballData;

use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;

interface PresupuestoPeticiones
{
    /**
     * Reserva un cupo para una petición.
     *
     * @throws LimiteDePeticionesAlcanzado si no queda cupo
     */
    public function reservar(): void;

    public function restantes(): int;

    /** Segundos hasta que se libere el próximo cupo (0 si hay uno disponible). */
    public function segundosParaReintentar(): int;

    /** Bloquea todas las peticiones un tiempo (el proveedor avisó que se agotó). */
    public function agotarPor(int $segundos): void;
}
