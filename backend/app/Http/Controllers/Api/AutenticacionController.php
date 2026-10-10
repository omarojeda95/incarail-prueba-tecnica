<?php

namespace App\Http\Controllers\Api;

use App\Aplicacion\Acceso\EmitirTokenAcceso;
use App\Aplicacion\Acceso\ReglasCasl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autenticacion\IniciarSesionRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AutenticacionController extends Controller
{
    public function iniciarSesion(IniciarSesionRequest $request, EmitirTokenAcceso $emitir, ReglasCasl $reglas): JsonResponse
    {
        $resultado = $emitir->ejecutar($request->validated('correo'), $request->validated('contrasena'));
        $usuario = $resultado->getToken()->user;

        return response()->json([
            'tipo_token' => 'Bearer',
            'token_acceso' => $resultado->accessToken,
            'expira_en' => $resultado->getToken()->expires_at?->toIso8601String(),
            ...$this->datosSesion($usuario, $reglas),
        ]);
    }

    public function sesion(Request $request, ReglasCasl $reglas): JsonResponse
    {
        return response()->json($this->datosSesion($request->user(), $reglas));
    }

    public function cerrarSesion(Request $request): Response
    {
        $request->user()->token()?->revoke();

        return response()->noContent();
    }

    /** @return array{usuario: UsuarioResource, habilidades: list<array{action: string, subject: string}>} */
    private function datosSesion(Usuario $usuario, ReglasCasl $reglas): array
    {
        return [
            'usuario' => new UsuarioResource($usuario->load('roles')),
            'habilidades' => $reglas->para($usuario),
        ];
    }
}
