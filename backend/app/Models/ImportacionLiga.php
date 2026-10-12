<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('importaciones_liga')]
#[WithoutTimestamps]
#[Fillable(['liga_id', 'cantidad_equipos', 'cantidad_jugadores', 'origen', 'importado_en'])]
class ImportacionLiga extends Model
{
    protected function casts(): array
    {
        return ['importado_en' => 'datetime'];
    }

    /** @return BelongsTo<Liga, $this> */
    public function liga(): BelongsTo
    {
        return $this->belongsTo(Liga::class);
    }
}
