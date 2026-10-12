<?php

namespace App\Aplicacion\Futbol\Consultas;

use DateTimeInterface;

final readonly class ResultadoConsulta
{
    public function __construct(
        public mixed $datos,
        public bool $desactualizado = false,
        public ?DateTimeInterface $sincronizadoEn = null,
        public string $fuente = 'base_de_datos',
        public ?int $reintentarEn = null,
    ) {}

    /** @return array<string, mixed> */
    public function meta(): array
    {
        return array_filter([
            'fuente' => $this->fuente,
            'desactualizado' => $this->desactualizado,
            'sincronizado_en' => $this->sincronizadoEn?->format(DATE_ATOM),
            'reintentar_en' => $this->reintentarEn,
        ], fn ($valor) => $valor !== null);
    }
}
