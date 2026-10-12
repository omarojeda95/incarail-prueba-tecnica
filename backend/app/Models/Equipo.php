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

#[Table('equipos')]
#[WithoutIncrementing]
#[Fillable([
    'id', 'nombre', 'tipo', 'nombre_corto', 'sigla', 'escudo', 'direccion', 'sitio_web', 'fundacion',
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

    /** @return BelongsToMany<Jugador, $this> */
    public function jugadores(): BelongsToMany
    {
        return $this->belongsToMany(Jugador::class, 'equipo_jugador')->using(EquipoJugador::class)->withTimestamps();
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
