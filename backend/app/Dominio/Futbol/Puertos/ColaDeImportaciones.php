<?php

namespace App\Dominio\Futbol\Puertos;

/**
 * Puerto para dejar una importación pendiente, que se hará cuando haya cupo de peticiones.
 * La aplicación no sabe cómo se encola: el adaptador usa la cola de Laravel.
 */
interface ColaDeImportaciones
{
    public function encolar(int|string $liga, string $origen): void;
}
