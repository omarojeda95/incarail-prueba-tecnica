<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Futbol\Consultas\ConsultasEquipos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Futbol\ListarEquiposRequest;
use App\Http\Resources\EquipoResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EquipoController extends Controller
{
    public function index(ListarEquiposRequest $request, ConsultasEquipos $equipos): AnonymousResourceCollection
    {
        return EquipoResource::collection($equipos->paginar($request->filtros()));
    }

    public function show(int $team_id, ConsultasEquipos $equipos): EquipoResource
    {
        return new EquipoResource($equipos->buscar($team_id));
    }
}
