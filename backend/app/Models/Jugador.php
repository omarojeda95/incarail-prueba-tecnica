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

    /**
     * Todos los equipos en cuyo plantel está: su club y, si la hay, su selección.
     *
     * @return BelongsToMany<Equipo, $this>
     */
    public function equipos(): BelongsToMany
    {
        return $this->belongsToMany(Equipo::class, 'equipo_jugador')->using(EquipoJugador::class)->withTimestamps();
    }

    /**
     * Club actual: el club de su plantel que trajo la importación más reciente (tras un
     * traspaso o un préstamo, el nuevo club). Null si solo se conoce su selección.
     */
    public function club(): ?Equipo
    {
        $equipos = $this->relationLoaded('equipos') ? $this->equipos : $this->equipos()->get();

        return $equipos
            ->where('tipo', 'club')
            ->sortByDesc(fn (Equipo $equipo) => $equipo->pivot->actualizado_en)
            ->first();
    }
}
