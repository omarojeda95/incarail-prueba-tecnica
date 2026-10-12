<?php

namespace App\Jobs;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportarLigaJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $uniqueFor = 900;

    public function __construct(public readonly int|string $liga, public readonly string $origen = 'cola') {}

    public function uniqueId(): string
    {
        return strtoupper((string) $this->liga);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function handle(ImportarLiga $importar): void
    {
        try {
            $importar->ejecutar($this->liga, $this->origen);
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->release($e->reintentarEn + random_int(1, 5));
        } catch (ProveedorNoDisponible) {
            $this->release(60);
        }
    }
}
