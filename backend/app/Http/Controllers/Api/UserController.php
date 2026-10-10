<?php

namespace App\Http\Controllers\Api;

use App\Application\Access\UserManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreUserRequest;
use App\Http\Requests\Access\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserManagement $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'between:1,100']]);

        $users = User::query()
            ->with('roles')
            ->when($request->query('search'), fn ($query, $search) => $query->where(
                fn ($query) => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($user, $request->validated())->load('roles'));
    }

    public function deactivate(Request $request, User $user): UserResource
    {
        return new UserResource($this->users->deactivate($user, $request->user())->load('roles'));
    }

    public function activate(User $user): UserResource
    {
        return new UserResource($this->users->activate($user)->load('roles'));
    }
}
