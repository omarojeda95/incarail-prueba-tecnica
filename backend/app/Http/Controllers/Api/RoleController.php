<?php

namespace App\Http\Controllers\Api;

use App\Application\Access\RoleManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SaveRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleManagement $roles) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->where('guard_name', config('acl.guard'))->with('permissions')->withCount('users')->orderBy('name')->get()
        );
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        $role = $this->roles->create($request->validated('name'), $request->validated('permissions'));

        return (new RoleResource($role->load('permissions')->loadCount('users')))->response()->setStatusCode(201);
    }

    public function update(SaveRoleRequest $request, Role $role): RoleResource
    {
        $role = $this->roles->update($role, $request->validated('name'), $request->validated('permissions'));

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): Response
    {
        $this->roles->delete($role);

        return response()->noContent();
    }

    /** The permission catalog, with its CASL equivalent, to build the role editor. */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => collect(config('acl.permissions'))
                ->map(fn (array $permission, string $name) => ['name' => $name, ...$permission])
                ->values(),
        ]);
    }
}
