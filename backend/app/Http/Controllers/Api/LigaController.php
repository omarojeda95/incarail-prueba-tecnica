<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Futbol\Consultas\CatalogoLigas;
use App\Aplicacion\Futbol\Consultas\DetalleLiga;
use App\Http\Controllers\Controller;
use App\Http\Requests\Futbol\ListarLigasRequest;
use App\Http\Resources\LigaResource;
use App\Jobs\ImportarLigaJob;
use App\Models\Liga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LigaController extends Controller
{
    public function index(ListarLigasRequest $request, CatalogoLigas $catalogo): AnonymousResourceCollection
    {
        $resultado = $catalogo->ejecutar($request->filtros());

        return LigaResource::collection($resultado->datos)->additional(['meta' => $resultado->meta()]);
    }

    public function show(string $competition_id, DetalleLiga $detalle): JsonResponse
    {
        $resultado = $detalle->ejecutar($competition_id);

        // Siempre 200: un JsonResource respondería 201 para un modelo recién importado.
        return (new LigaResource($resultado->datos))
            ->additional(['meta' => $resultado->meta()])
            ->response()
            ->setStatusCode(200);
    }

    /** Encola la actualización de una liga ya listada (se ejecuta respetando el límite de peticiones). */
    public function importar(string $competition_id): JsonResponse
    {
        $liga = Liga::query()->referencia($competition_id)->firstOrFail();

        ImportarLigaJob::dispatch($liga->id, 'manual');

        return response()->json([
            'mensaje' => 'La importación quedó en cola y se ejecutará respetando el límite de football-data.',
            'data' => ['liga_id' => $liga->id, 'en_cola' => true],
        ], 202);
    }
}
