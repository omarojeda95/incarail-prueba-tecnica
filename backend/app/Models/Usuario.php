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

    protected string $guard_name = 'api';

    protected $authPasswordName = 'contrasena';

    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'contrasena' => 'hashed',
            'activo' => 'boolean',
            'desactivado_en' => 'datetime',
        ];
    }

    protected function correo(): Attribute
    {
        return Attribute::set(fn (string $valor) => strtolower(trim($valor)));
    }
}
