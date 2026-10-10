<?php

namespace App\Application\Football\Queries;

use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Queries over the teams stored so far. They never call football-data. */
final class TeamQueries
{
    /** @param  array{search?: string, competition_id?: int, per_page?: int}  $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Team::query()
            ->withCount('players')
            ->with('competitions:id,name,code,emblem')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")
                    ->orWhereLike('short_name', "%{$search}%")
                    ->orWhereLike('tla', "%{$search}%")
            ))
            ->when($filters['competition_id'] ?? null, fn (Builder $query, int $competitionId) => $query->whereRelation('competitions', 'competitions.id', $competitionId))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();
    }

    public function find(int $id): Team
    {
        return Team::query()
            ->with([
                'competitions:id,name,code,emblem,area_name',
                'players' => fn ($query) => $query->orderBy('position')->orderBy('shirt_number')->orderBy('name'),
            ])
            ->findOrFail($id);
    }
}
