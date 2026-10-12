<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['liga_id', 'equipo_id', 'temporada'])]
class LigaEquipo extends Pivot
{
    use MarcasDeTiempo;

    protected $table = 'liga_equipo';
}
