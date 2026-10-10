<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Database\Factories\LigaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('ligas')]
#[WithoutIncrementing]
#[Fillable([
    'id', 'nombre', 'codigo', 'tipo', 'emblema', 'plan', 'area_nombre', 'area_codigo', 'area_bandera',
    'temporada_inicio', 'temporada_fin', 'jornada_actual', 'actualizado_proveedor_en', 'equipos_sincronizados_en',
])]
class Liga extends Model
{
    /** @use HasFactory<LigaFactory> */
    use HasFactory, MarcasDeTiempo;

    protected function casts(): array
    {
        return [
            'temporada_inicio' => 'date',
            'temporada_fin' => 'date',
            'actualizado_proveedor_en' => 'datetime',
            'equipos_sincronizados_en' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Equipo, $this> */
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'liga_equipo')
            ->using(LigaEquipo::class)
            ->withPivot('temporada')
            ->withTimestamps();
    }

    /**
     * Solo las filas del pivote: contarlas evita unir la tabla de equipos
     * (withCount('equipos') unía 5.000 equipos por liga: 213 ms -> 3 ms).
     *
     * @return HasMany<LigaEquipo, $this>
     */
    public function vinculosEquipos(): HasMany
    {
        return $this->hasMany(LigaEquipo::class);
    }

    /** @return HasMany<ImportacionLiga, $this> */
    public function importaciones(): HasMany
    {
        return $this->hasMany(ImportacionLiga::class);
    }

    /** @return HasOne<ImportacionLiga, $this> */
    public function ultimaImportacion(): HasOne
    {
        return $this->hasOne(ImportacionLiga::class)->latestOfMany('importado_en');
    }

    /** Busca por id de football-data (2021) o por código (PL). */
    #[Scope]
    protected function referencia(Builder $query, string|int $referencia): void
    {
        is_numeric($referencia)
            ? $query->whereKey((int) $referencia)
            : $query->where('codigo', strtoupper($referencia));
    }

    #[Scope]
    protected function importadas(Builder $query): void
    {
        $query->whereNotNull('equipos_sincronizados_en');
    }

    public function estaImportada(): bool
    {
        return $this->equipos_sincronizados_en !== null;
    }

    public function esImportable(): bool
    {
        return in_array($this->plan, config('futbol.planes_disponibles'), true);
    }

    public function estaVencida(): bool
    {
        return ! $this->estaImportada()
            || $this->equipos_sincronizados_en->lt(now()->subMinutes(config('futbol.vigencia.detalle_liga_minutos')));
    }
}
