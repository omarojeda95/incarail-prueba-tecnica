<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[WithoutIncrementing]
#[Fillable([
    'id', 'name', 'short_name', 'tla', 'crest', 'address', 'website', 'founded',
    'club_colors', 'venue', 'area_name', 'coach_name', 'provider_updated_at',
])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['provider_updated_at' => 'datetime'];
    }

    /** @return HasMany<Player, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return BelongsToMany<Competition, $this> */
    public function competitions(): BelongsToMany
    {
        return $this->belongsToMany(Competition::class)->withPivot('season')->withTimestamps();
    }
}
