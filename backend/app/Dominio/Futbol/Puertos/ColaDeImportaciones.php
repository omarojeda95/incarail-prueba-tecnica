<?php

namespace App\Dominio\Futbol\Puertos;

interface ColaDeImportaciones
{
    public function encolar(int|string $liga, string $origen): void;
}
