<?php

namespace App\Application\Football\Queries;

use App\Models\Player;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Queries over the players stored so far. They never call football-data. */
final class PlayerQueries
{
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

    /** @return list<string> */
    public function positions(): array
    {
        return Player::query()->whereNotNull('position')->distinct()->orderBy('position')->pluck('position')->all();
    }
}
