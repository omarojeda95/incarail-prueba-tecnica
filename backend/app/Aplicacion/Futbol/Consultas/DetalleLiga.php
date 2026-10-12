<?php

namespace App\Aplicacion\Futbol\Consultas;

use App\Aplicacion\Futbol\ImportarLiga;
use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use App\Models\Liga;

/**
 * Consulta: una liga con sus equipos. Caché de lectura sobre nuestra base:
 *  - importada y vigente          -> se responde local, sin petición externa
 *  - no importada o vencida       -> se importa ahora (1 petición a football-data)
 *  - sin cupo / proveedor caído   -> copia local desactualizada si existe, y la
 *                                    importación queda en cola hasta que haya cupo
 */
final class DetalleLiga
{
    public function __construct(
        private readonly ImportarLiga $importar,
        private readonly ColaDeImportaciones $cola,
    ) {}

    public function ejecutar(string $referencia): ResultadoConsulta
    {
        $local = Liga::query()->referencia($referencia)->first();

        if ($local?->estaImportada() && ! $local->estaVencida()) {
            return $this->resultado($local, desactualizado: false, fuente: 'base_de_datos');
        }

        if ($local?->plan !== null && ! $local->esImportable()) {
            return $local->estaImportada()
                ? $this->resultado($local, desactualizado: true, fuente: 'base_de_datos')
                : throw new LigaFueraDelPlan("La liga {$local->codigo} no está incluida en el plan de football-data.");
        }

        try {
            return $this->resultado($this->importar->ejecutar($local?->id ?? $referencia), desactualizado: false, fuente: 'football-data');
        } catch (LimiteDePeticionesAlcanzado $e) {
            $this->cola->encolar($local?->id ?? strtoupper($referencia), 'cola');

            if ($local?->estaImportada()) {
                return $this->resultado($local, desactualizado: true, fuente: 'base_de_datos', reintentarEn: $e->reintentarEn);
            }

            throw new LimiteDePeticionesAlcanzado($e->reintentarEn, enCola: true);
        } catch (ProveedorNoDisponible $e) {
            if ($local?->estaImportada()) {
                return $this->resultado($local, desactualizado: true, fuente: 'base_de_datos');
            }

            throw $e;
        }
    }

    private function resultado(Liga $liga, bool $desactualizado, string $fuente, ?int $reintentarEn = null): ResultadoConsulta
    {
        $liga->load([
            'equipos' => fn ($query) => $query->withCount('jugadores')->orderBy('nombre'),
            'ultimaImportacion',
        ]);

        return new ResultadoConsulta($liga, $desactualizado, $liga->equipos_sincronizados_en, $fuente, $reintentarEn);
    }
}
