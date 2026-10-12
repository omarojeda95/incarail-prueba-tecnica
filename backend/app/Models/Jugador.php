<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Table('jugadores')]
#[WithoutIncrementing]
#[Fillable(['id', 'nombre', 'posicion', 'numero_camiseta', 'fecha_nacimiento', 'nacionalidad'])]
class Jugador extends Model
{
    /** @use HasFactory<JugadorFactory> */
    use HasFactory, MarcasDeTiempo;

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    /** @return BelongsToMany<Equipo, $this> */
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_jugador')->using(EquipoJugador::class)->withTimestamps();
    }

    /** El club de la importación más reciente (tras un traspaso, el nuevo); null si solo se conoce su selección. */
    public function club(): ?Equipo
    {
        $equipos = $this->relationLoaded('equipos') ? $this->equipos : $this->equipos()->get();

        // Si empatan, el de menor id, para que el resultado no dependa del orden de la consulta.
        return $equipos
            ->where('tipo', 'club')
            ->sortBy([
                fn (Equipo $a, Equipo $b) => strval($b->pivot->actualizado_en) <=> strval($a->pivot->actualizado_en),
                fn (Equipo $a, Equipo $b) => $a->id <=> $b->id,
            ])
            ->first();
    }
}
