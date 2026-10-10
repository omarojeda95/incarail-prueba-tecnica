<?php

namespace App\Models;

use App\Models\Concerns\MarcasDeTiempo;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Table('usuarios')]
#[Fillable(['nombre', 'correo', 'contrasena', 'activo', 'desactivado_en'])]
#[Hidden(['contrasena'])]
class Usuario extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, HasRoles, MarcasDeTiempo;

    /** Roles y permisos se definen para el guard de Passport. */
    protected string $guard_name = 'api';

    /** Columna de la contraseña para la autenticación de Laravel. */
    protected $authPasswordName = 'contrasena';

    /** Sin "recordarme": la API usa tokens, no sesiones. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'contrasena' => 'hashed',
            'activo' => 'boolean',
            'desactivado_en' => 'datetime',
        ];
    }

    /** El correo se guarda en minúsculas (la base también lo exige: usuarios_correo_minusculas_unique). */
    protected function correo(): Attribute
    {
        return Attribute::set(fn (string $valor) => strtolower(trim($valor)));
    }
}
