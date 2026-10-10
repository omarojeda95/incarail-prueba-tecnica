<?php

namespace App\Models\Concerns;

/** Columnas de auditoría de Eloquent con nombres en español. */
trait MarcasDeTiempo
{
    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';
}
