<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Futbol\Consultas\ConsultasJugadores;
use App\Http\Controllers\Controller;
use App\Http\Requests\Futbol\ListarJugadoresRequest;
use App\Http\Resources\JugadorResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JugadorController extends Controller
{
    public function index(ListarJugadoresRequest $request, ConsultasJugadores $jugadores): AnonymousResourceCollection
    {
        return JugadorResource::collection($jugadores->paginar($request->filtros()))
            ->additional(['filtros' => ['posiciones' => $jugadores->posiciones()]]);
    }

    public function show(int $player_id, ConsultasJugadores $jugadores): JugadorResource
    {
        return new JugadorResource($jugadores->buscar($player_id));
    }
}
