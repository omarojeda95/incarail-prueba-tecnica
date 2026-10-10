<?php

namespace App\Http\Middleware;

use App\Dominio\Acceso\Excepciones\CuentaDesactivada;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Un usuario desactivado pierde el acceso al instante, aunque su token siga vigente. */
class ExigirUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->activo) {
            $usuario->token()?->revoke();

            throw new CuentaDesactivada;
        }

        return $next($request);
    }
}
