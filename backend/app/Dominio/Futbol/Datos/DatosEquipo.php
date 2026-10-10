<?php

namespace App\Dominio\Futbol\Datos;

use DateTimeImmutable;

final readonly class DatosEquipo
{
    /**
     * @param  list<DatosJugador>  $plantel
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $nombreCorto,
        public ?string $sigla,
        public ?string $escudo,
        public ?string $direccion,
        public ?string $sitioWeb,
        public ?int $fundacion,
        public ?string $colores,
        public ?string $estadio,
        public ?string $areaNombre,
        public ?string $entrenador,
        public ?DateTimeImmutable $actualizadoEn,
        public array $plantel = [],
    ) {}
}
