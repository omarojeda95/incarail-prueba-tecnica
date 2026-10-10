<?php

namespace App\Http\Resources;

use App\Aplicacion\Acceso\GestionRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/** @mixin Role */
class RolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'protegido' => GestionRoles::esProtegido($this->resource),
            'permisos' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->sort()->values()),
            'cantidad_usuarios' => $this->whenCounted('users'),
        ];
    }
}
