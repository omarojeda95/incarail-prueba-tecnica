<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Vínculo de una liga con un equipo en una temporada (clave compuesta). */
#[Fillable(['liga_id', 'equipo_id', 'temporada'])]
class LigaEquipo extends Pivot
{
    use MarcasDeTiempo;

    protected $table = 'liga_equipo';
}
