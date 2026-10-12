<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Pertenencia de un jugador al plantel de un equipo: su club o su selección (clave compuesta). */
#[Fillable(['equipo_id', 'jugador_id'])]
class EquipoJugador extends Pivot
{
    use MarcasDeTiempo;

    protected $table = 'equipo_jugador';
}
