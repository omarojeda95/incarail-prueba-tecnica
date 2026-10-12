<?php

namespace App\Aplicacion\Futbol\Consultas;

use App\Aplicacion\Futbol\SincronizarCatalogoLigas;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Models\Liga;
use Illuminate\Database\Eloquent\Builder;

/** Si el catálogo está vencido intenta refrescarlo; si falla, responde lo guardado. */
final class CatalogoLigas
{
    public function __construct(private readonly SincronizarCatalogoLigas $sincronizar) {}

    /** @param  array{buscar?: string, area?: string, importadas?: bool, disponibles?: bool}  $filtros */
    public function ejecutar(array $filtros = []): ResultadoConsulta
    {
        $desactualizado = false;

        if ($this->sincronizar->estaVencido()) {
            try {
                $this->sincronizar->ejecutar();
            } catch (LimiteDePeticionesAlcanzado|ProveedorNoDisponible $e) {
                if (! Liga::query()->exists()) {
                    throw $e;
                }
                $desactualizado = true;
            }
        }

        $ligas = Liga::query()
            ->withCount('vinculosEquipos as cantidad_equipos')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $texto) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$texto}%")
                    ->orWhereLike('codigo', "%{$texto}%")
                    ->orWhereLike('area_nombre', "%{$texto}%")
            ))
            ->when($filtros['area'] ?? null, fn (Builder $query, string $area) => $query->where('area_nombre', $area))
            ->when(isset($filtros['importadas']), fn (Builder $query) => $filtros['importadas']
                ? $query->whereNotNull('equipos_sincronizados_en')
                : $query->whereNull('equipos_sincronizados_en'))
            ->when(isset($filtros['disponibles']), fn (Builder $query) => $filtros['disponibles']
                ? $query->whereIn('plan', config('futbol.planes_disponibles'))
                : $query->whereNotIn('plan', config('futbol.planes_disponibles')))
            ->orderBy('area_nombre')
            ->orderBy('nombre')
            ->get();

        return new ResultadoConsulta($ligas, desactualizado: $desactualizado, sincronizadoEn: $this->sincronizar->ultimaSincronizacion());
    }
}
