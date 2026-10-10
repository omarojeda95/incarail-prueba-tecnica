<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Acceso\GestionRoles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Acceso\GuardarRolRequest;
use App\Http\Resources\RolResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    public function __construct(private readonly GestionRoles $roles) {}

    public function index(): AnonymousResourceCollection
    {
        return RolResource::collection(
            Role::query()->where('guard_name', config('acl.guard'))->with('permissions')->withCount('users')->orderBy('name')->get()
        );
    }

    public function store(GuardarRolRequest $request): JsonResponse
    {
        $rol = $this->roles->crear($request->validated('nombre'), $request->validated('permisos'));

        return (new RolResource($rol->load('permissions')->loadCount('users')))->response()->setStatusCode(201);
    }

    public function update(GuardarRolRequest $request, Role $rol): RolResource
    {
        $rol = $this->roles->editar($rol, $request->validated('nombre'), $request->validated('permisos'));

        return new RolResource($rol->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $rol): Response
    {
        $this->roles->eliminar($rol);

        return response()->noContent();
    }

    /** Catálogo de permisos con su equivalente CASL, para el editor de roles. */
    public function permisos(): JsonResponse
    {
        return response()->json([
            'data' => collect(config('acl.permisos'))
                ->map(fn (array $permiso, string $nombre) => ['nombre' => $nombre, ...$permiso])
                ->values(),
        ]);
    }
}
