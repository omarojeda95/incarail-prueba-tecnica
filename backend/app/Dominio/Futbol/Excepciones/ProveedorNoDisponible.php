<?php

namespace App\Dominio\Futbol\Excepciones;

use Throwable;

final class ProveedorNoDisponible extends ExcepcionDatosFutbol
{
    public function __construct(
        string $mensaje,
        public readonly bool $sinConexion = false,
        ?Throwable $anterior = null,
        public readonly bool $sinConfigurar = false,
    ) {
        parent::__construct($mensaje, 0, $anterior);
    }

    public static function sinConfigurar(): self
    {
        return new self('FOOTBALL_DATA_TOKEN no está configurado.', sinConfigurar: true);
    }
}
