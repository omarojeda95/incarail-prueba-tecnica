<?php

namespace App\Dominio\Futbol\Datos;

use DateTimeImmutable;

final readonly class DatosJugador
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $posicion,
        public ?int $numeroCamiseta,
        public ?DateTimeImmutable $fechaNacimiento,
        public ?string $nacionalidad,
    ) {}
}
