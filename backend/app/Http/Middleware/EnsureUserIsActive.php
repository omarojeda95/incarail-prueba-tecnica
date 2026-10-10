<?php

namespace App\Http\Middleware;

use App\Domain\Access\Exceptions\AccountDisabled;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A deactivated user loses access immediately, even with a token still valid. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->token()?->revoke();

            throw new AccountDisabled;
        }

        return $next($request);
    }
}
