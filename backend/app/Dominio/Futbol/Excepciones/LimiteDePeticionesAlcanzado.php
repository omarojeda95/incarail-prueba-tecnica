<?php

namespace App\Dominio\Futbol\Excepciones;

/** Se agotó el presupuesto de peticiones al proveedor (el nuestro o el suyo). */
final class LimiteDePeticionesAlcanzado extends ExcepcionDatosFutbol
{
    public function __construct(public readonly int $reintentarEn, public readonly bool $enCola = false)
    {
        parent::__construct("Límite de peticiones a football-data alcanzado; reintentar en {$reintentarEn} s.");
    }
}
