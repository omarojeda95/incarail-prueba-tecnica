<?php

namespace App\Http\Resources;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Competition */
class CompetitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'emblem' => $this->emblem,
            'plan' => $this->plan,
            'available' => $this->isImportable(),
            'area' => [
                'name' => $this->area_name,
                'code' => $this->area_code,
                'flag' => $this->area_flag,
            ],
            'season' => [
                'start' => $this->season_start?->toDateString(),
                'end' => $this->season_end?->toDateString(),
                'current_matchday' => $this->current_matchday,
            ],
            'imported' => $this->isImported(),
            'teams_synced_at' => $this->teams_synced_at?->toIso8601String(),
            'teams_count' => $this->whenCounted('teams'),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'last_import' => $this->whenLoaded('latestImport', fn () => $this->latestImport ? [
                'teams_count' => $this->latestImport->teams_count,
                'players_count' => $this->latestImport->players_count,
                'trigger' => $this->latestImport->trigger,
                'imported_at' => $this->latestImport->imported_at->toIso8601String(),
            ] : null),
        ];
    }
}
