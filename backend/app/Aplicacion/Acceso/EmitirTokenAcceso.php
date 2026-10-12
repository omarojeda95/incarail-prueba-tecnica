<?php

namespace App\Aplicacion\Acceso;

use App\Dominio\Acceso\Excepciones\CredencialesInvalidas;
use App\Dominio\Acceso\Excepciones\CuentaDesactivada;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\PersonalAccessTokenResult;

final class EmitirTokenAcceso
{
    public function ejecutar(string $correo, string $contrasena): PersonalAccessTokenResult
    {
        $usuario = Usuario::query()->where('correo', strtolower(trim($correo)))->first();

        // Hash::check corre aunque el usuario no exista, para que el tiempo de respuesta no lo delate.
        if (! Hash::check($contrasena, $usuario->contrasena ?? Hash::make(str()->random())) || ! $usuario) {
            throw new CredencialesInvalidas;
        }

        if (! $usuario->activo) {
            throw new CuentaDesactivada;
        }

        return $usuario->createToken('spa');
    }
}
