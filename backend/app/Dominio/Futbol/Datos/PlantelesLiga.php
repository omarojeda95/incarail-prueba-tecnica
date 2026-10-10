<?php

namespace App\Dominio\Futbol\Datos;

/** Una liga con los equipos (y sus planteles) de una temporada: lo que necesita una importación. */
final readonly class PlantelesLiga
{
    /**
     * @param  list<DatosEquipo>  $equipos
     */
    public function __construct(
        public DatosLiga $liga,
        public ?string $temporada,
        public array $equipos,
    ) {}

    public function cantidadJugadores(): int
    {
        return array_sum(array_map(fn (DatosEquipo $equipo) => count($equipo->plantel), $this->equipos));
    }
}
