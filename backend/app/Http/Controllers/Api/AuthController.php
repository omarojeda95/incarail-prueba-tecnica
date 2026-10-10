<?php

namespace App\Http\Controllers\Api;

use App\Application\Access\AbilityRules;
use App\Application\Access\IssueAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function login(LoginRequest $request, IssueAccessToken $issue, AbilityRules $rules): JsonResponse
    {
        $result = $issue->handle($request->validated('email'), $request->validated('password'));
        $user = $result->getToken()->user;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $result->accessToken,
            'expires_at' => $result->getToken()->expires_at?->toIso8601String(),
            ...$this->session($user, $rules),
        ]);
    }

    public function me(Request $request, AbilityRules $rules): JsonResponse
    {
        return response()->json($this->session($request->user(), $rules));
    }

    public function logout(Request $request): Response
    {
        $request->user()->token()?->revoke();

        return response()->noContent();
    }

    /** @return array{user: UserResource, abilities: list<array{action: string, subject: string}>} */
    private function session(User $user, AbilityRules $rules): array
    {
        return [
            'user' => new UserResource($user->load('roles')),
            'abilities' => $rules->for($user),
        ];
    }
}
