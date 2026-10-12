<?php

namespace App\Dominio\Futbol\Datos;

use DateTimeImmutable;

final readonly class DatosLiga
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $codigo,
        public ?string $tipo,
        public ?string $emblema,
        public ?string $plan,
        public ?string $areaNombre,
        public ?string $areaCodigo,
        public ?string $areaBandera,
        public ?DateTimeImmutable $temporadaInicio,
        public ?DateTimeImmutable $temporadaFin,
        public ?int $jornadaActual,
        public ?DateTimeImmutable $actualizadoEn,
    ) {}
}
