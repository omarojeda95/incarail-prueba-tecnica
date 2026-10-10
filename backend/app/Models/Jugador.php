<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Database\Factories\JugadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('jugadores')]
#[WithoutIncrementing]
#[Fillable(['id', 'equipo_id', 'nombre', 'posicion', 'numero_camiseta', 'fecha_nacimiento', 'nacionalidad'])]
class Jugador extends Model
{
    /** @use HasFactory<JugadorFactory> */
    use HasFactory, MarcasDeTiempo;

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    /** @return BelongsTo<Equipo, $this> */
    public function equipo(): BelongsTo
    {
        return $this->belongsTo(Equipo::class);
    }
}
