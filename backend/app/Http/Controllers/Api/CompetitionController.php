<?php

namespace App\Http\Controllers\Api;

use App\Application\Football\Queries\CompetitionCatalog;
use App\Application\Football\Queries\CompetitionDetail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Football\ListCompetitionsRequest;
use App\Http\Resources\CompetitionResource;
use App\Jobs\ImportCompetitionJob;
use App\Models\Competition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompetitionController extends Controller
{
    public function index(ListCompetitionsRequest $request, CompetitionCatalog $catalog): AnonymousResourceCollection
    {
        $result = $catalog->handle($request->filters());

        return CompetitionResource::collection($result->data)->additional(['meta' => $result->meta()]);
    }

    public function show(string $competition_id, CompetitionDetail $detail): JsonResponse
    {
        $result = $detail->handle($competition_id);

        // Always 200: a JsonResource would answer 201 for a just-imported model.
        return (new CompetitionResource($result->data))
            ->additional(['meta' => $result->meta()])
            ->response()
            ->setStatusCode(200);
    }

    /** Queue a refresh of an already listed competition (runs within the request budget). */
    public function import(string $competition_id): JsonResponse
    {
        $competition = Competition::query()->reference($competition_id)->firstOrFail();

        ImportCompetitionJob::dispatch($competition->id, 'manual');

        return response()->json([
            'message' => 'La importación quedó en cola y se ejecutará respetando el límite de football-data.',
            'data' => ['competition_id' => $competition->id, 'queued' => true],
        ], 202);
    }
}
