<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Database\Factories\EquipoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('equipos')]
#[WithoutIncrementing]
#[Fillable([
    'id', 'nombre', 'nombre_corto', 'sigla', 'escudo', 'direccion', 'sitio_web', 'fundacion',
    'colores', 'estadio', 'area_nombre', 'entrenador', 'actualizado_proveedor_en',
])]
class Equipo extends Model
{
    /** @use HasFactory<EquipoFactory> */
    use HasFactory, MarcasDeTiempo;

    protected function casts(): array
    {
        return ['actualizado_proveedor_en' => 'datetime'];
    }

    /** @return HasMany<Jugador, $this> */
    public function jugadores(): HasMany
    {
        return $this->hasMany(Jugador::class);
    }

    /** @return BelongsToMany<Liga, $this> */
    public function ligas(): BelongsToMany
    {
        return $this->belongsToMany(Liga::class, 'liga_equipo')
            ->using(LigaEquipo::class)
            ->withPivot('temporada')
            ->withTimestamps();
    }
}
