<?php

namespace App\Aplicacion\Futbol\Consultas;

use App\Models\Equipo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Consultas sobre los equipos guardados. Nunca llaman a football-data. */
final class ConsultasEquipos
{
    /** @param  array{buscar?: string, liga_id?: int, tipo?: string, por_pagina?: int}  $filtros */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        return Equipo::query()
            ->withCount('jugadores')
            ->with('ligas:id,nombre,codigo,emblema')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $texto) => $query->where(
                fn (Builder $query) => $query->whereLike('nombre', "%{$texto}%")
                    ->orWhereLike('nombre_corto', "%{$texto}%")
                    ->orWhereLike('sigla', "%{$texto}%")
            ))
            ->when($filtros['liga_id'] ?? null, fn (Builder $query, int $ligaId) => $query->whereRelation('ligas', 'ligas.id', $ligaId))
            ->when($filtros['tipo'] ?? null, fn (Builder $query, string $tipo) => $query->where('tipo', $tipo))
            ->orderBy('nombre')
            ->paginate($filtros['por_pagina'] ?? 20)
            ->withQueryString();
    }

    public function buscar(int $id): Equipo
    {
        return Equipo::query()
            ->with([
                'ligas:id,nombre,codigo,emblema,area_nombre',
                'jugadores' => fn ($query) => $query->orderBy('posicion')->orderBy('numero_camiseta')->orderBy('nombre'),
            ])
            ->findOrFail($id);
    }
}
