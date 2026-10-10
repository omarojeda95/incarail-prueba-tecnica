<?php

namespace App\Http\Controllers\Api;

use App\Application\Football\Queries\TeamQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Football\ListTeamsRequest;
use App\Http\Resources\TeamResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    public function index(ListTeamsRequest $request, TeamQueries $teams): AnonymousResourceCollection
    {
        return TeamResource::collection($teams->paginate($request->filters()));
    }

    public function show(int $team_id, TeamQueries $teams): TeamResource
    {
        return new TeamResource($teams->find($team_id));
    }
}
