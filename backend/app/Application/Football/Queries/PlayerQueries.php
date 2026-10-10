<?php

namespace App\Application\Football\Queries;

use App\Models\Player;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/** Queries over the players stored so far. They never call football-data. */
final class PlayerQueries
{
    /** Forgotten by ForgetPlayerPositions whenever a league is imported. */
    public const POSITIONS_CACHE_KEY = 'football:player-positions';

    private const SORTABLE = ['name', 'position', 'shirt_number', 'date_of_birth', 'nationality'];

    /**
     * @param  array{search?: string, team_id?: int, position?: string, nationality?: string, sort?: string, direction?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'name';

        return Player::query()
            ->with('team:id,name,short_name,crest')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereLike('name', "%{$search}%"))
            ->when($filters['team_id'] ?? null, fn (Builder $query, int $teamId) => $query->where('team_id', $teamId))
            ->when($filters['position'] ?? null, fn (Builder $query, string $position) => $query->where('position', $position))
            ->when($filters['nationality'] ?? null, fn (Builder $query, string $nationality) => $query->where('nationality', $nationality))
            ->orderBy($sort, ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function find(int $id): Player
    {
        return Player::query()->with('team:id,name,short_name,crest')->findOrFail($id);
    }

    /**
     * Distinct positions for the filter. A DISTINCT over every player costs a
     * full scan, and the list only changes when a league is imported.
     *
     * @return list<string>
     */
    public function positions(): array
    {
        return Cache::rememberForever(self::POSITIONS_CACHE_KEY, fn () => Player::query()
            ->whereNotNull('position')
            ->distinct()
            ->orderBy('position')
            ->pluck('position')
            ->all());
    }
}
