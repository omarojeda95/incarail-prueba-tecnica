<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['equipo_id', 'jugador_id'])]
class EquipoJugador extends Pivot
{
    use MarcasDeTiempo;

    protected $table = 'equipo_jugador';
}
