<?php

namespace App\Http\Controllers\Api;

use App\Application\Football\Queries\PlayerQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Football\ListPlayersRequest;
use App\Http\Resources\PlayerResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlayerController extends Controller
{
    public function index(ListPlayersRequest $request, PlayerQueries $players): AnonymousResourceCollection
    {
        return PlayerResource::collection($players->paginate($request->filters()))
            ->additional(['filters' => ['positions' => $players->positions()]]);
    }

    public function show(int $player_id, PlayerQueries $players): PlayerResource
    {
        return new PlayerResource($players->find($player_id));
    }
}
