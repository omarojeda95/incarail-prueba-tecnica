<?php

namespace App\Infraestructura\Colas;

use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use App\Jobs\ImportarLigaJob;

/** Adaptador del puerto: encola un ImportarLigaJob (único por liga). */
final class ColaDeImportacionesLaravel implements ColaDeImportaciones
{
    public function encolar(int|string $liga, string $origen): void
    {
        ImportarLigaJob::dispatch($liga, $origen);
    }
}
