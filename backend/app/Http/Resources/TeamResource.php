<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Team */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'tla' => $this->tla,
            'crest' => $this->crest,
            'address' => $this->address,
            'website' => $this->website,
            'founded' => $this->founded,
            'club_colors' => $this->club_colors,
            'venue' => $this->venue,
            'area' => $this->area_name,
            'coach' => $this->coach_name,
            'players_count' => $this->whenCounted('players'),
            'competitions' => $this->whenLoaded('competitions', fn () => $this->competitions->map(fn ($competition) => [
                'id' => $competition->id,
                'name' => $competition->name,
                'code' => $competition->code,
                'emblem' => $competition->emblem,
                'season' => $competition->pivot?->season,
            ])),
            'players' => PlayerResource::collection($this->whenLoaded('players')),
        ];
    }
}
