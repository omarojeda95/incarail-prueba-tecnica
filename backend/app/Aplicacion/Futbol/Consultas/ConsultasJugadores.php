<?php

namespace App\Aplicacion\Futbol\Consultas;

use App\Models\Jugador;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/** Consultas sobre los jugadores guardados. Nunca llaman a football-data. */
final class ConsultasJugadores
{
    /** La olvida el listener OlvidarPosicionesJugadores cada vez que se importa una liga. */
    public const CLAVE_CACHE_POSICIONES = 'futbol:posiciones-jugadores';

    private const ORDENABLES = ['nombre', 'posicion', 'numero_camiseta', 'fecha_nacimiento', 'nacionalidad'];

    /**
     * @param  array{buscar?: string, equipo_id?: int, posicion?: string, nacionalidad?: string, orden?: string, direccion?: string, por_pagina?: int}  $filtros
     */
    public function paginar(array $filtros): LengthAwarePaginator
    {
        $orden = in_array($filtros['orden'] ?? null, self::ORDENABLES, true) ? $filtros['orden'] : 'nombre';

        return Jugador::query()
            ->with('equipo:id,nombre,nombre_corto,escudo')
            ->when($filtros['buscar'] ?? null, fn (Builder $query, string $texto) => $query->whereLike('nombre', "%{$texto}%"))
            ->when($filtros['equipo_id'] ?? null, fn (Builder $query, int $equipoId) => $query->where('equipo_id', $equipoId))
            ->when($filtros['posicion'] ?? null, fn (Builder $query, string $posicion) => $query->where('posicion', $posicion))
            ->when($filtros['nacionalidad'] ?? null, fn (Builder $query, string $nacionalidad) => $query->where('nacionalidad', $nacionalidad))
            ->orderBy($orden, ($filtros['direccion'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($filtros['por_pagina'] ?? 25)
            ->withQueryString();
    }

    public function buscar(int $id): Jugador
    {
        return Jugador::query()->with('equipo:id,nombre,nombre_corto,escudo')->findOrFail($id);
    }

    /**
     * Posiciones distintas para el filtro. Un DISTINCT sobre todos los
     * jugadores recorre la tabla, y la lista solo cambia al importar una liga.
     *
     * @return list<string>
     */
    public function posiciones(): array
    {
        return Cache::rememberForever(self::CLAVE_CACHE_POSICIONES, fn () => Jugador::query()
            ->whereNotNull('posicion')
            ->distinct()
            ->orderBy('posicion')
            ->pluck('posicion')
            ->all());
    }
}
