<?php

namespace App\Jobs;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Importa una liga en segundo plano. Si se agotó el presupuesto de
 * peticiones, el job vuelve a la cola justo hasta que se libere un cupo:
 * el worker nunca supera las 10 peticiones por minuto y nada se pierde.
 */
class ImportarLigaJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Errores inesperados permitidos antes de rendirse (las devoluciones a la cola no cuentan). */
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
