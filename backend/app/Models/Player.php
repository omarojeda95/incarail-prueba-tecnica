<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[WithoutIncrementing]
#[Fillable(['id', 'team_id', 'name', 'position', 'shirt_number', 'date_of_birth', 'nationality'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
