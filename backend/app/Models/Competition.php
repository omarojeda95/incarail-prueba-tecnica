<?php

namespace App\Models;

use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[WithoutIncrementing]
#[Fillable([
    'id', 'name', 'code', 'type', 'emblem', 'plan', 'area_name', 'area_code', 'area_flag',
    'season_start', 'season_end', 'current_matchday', 'provider_updated_at', 'teams_synced_at',
])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'season_start' => 'date',
            'season_end' => 'date',
            'provider_updated_at' => 'datetime',
            'teams_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('season')->withTimestamps();
    }

    /** @return HasMany<CompetitionImport, $this> */
    public function imports(): HasMany
    {
        return $this->hasMany(CompetitionImport::class);
    }

    /** @return HasOne<CompetitionImport, $this> */
    public function latestImport(): HasOne
    {
        return $this->hasOne(CompetitionImport::class)->latestOfMany('imported_at');
    }

    /** Find by football-data id (2021) or code (PL). */
    #[Scope]
    protected function reference(Builder $query, string|int $reference): void
    {
        is_numeric($reference)
            ? $query->whereKey((int) $reference)
            : $query->where('code', strtoupper($reference));
    }

    #[Scope]
    protected function imported(Builder $query): void
    {
        $query->whereNotNull('teams_synced_at');
    }

    public function isImported(): bool
    {
        return $this->teams_synced_at !== null;
    }

    public function isImportable(): bool
    {
        return in_array($this->plan, config('football.available_plans'), true);
    }

    public function isStale(): bool
    {
        return ! $this->isImported()
            || $this->teams_synced_at->lt(now()->subMinutes(config('football.freshness.competition_detail_minutes')));
    }
}
