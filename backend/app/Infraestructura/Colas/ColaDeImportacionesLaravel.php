<?php

namespace App\Infraestructura\Colas;

use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use App\Jobs\ImportarLigaJob;

final class ColaDeImportacionesLaravel implements ColaDeImportaciones
{
    public function encolar(int|string $liga, string $origen): void
    {
        ImportarLigaJob::dispatch($liga, $origen);
    }
}
