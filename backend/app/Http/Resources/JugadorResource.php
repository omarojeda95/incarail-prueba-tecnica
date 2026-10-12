<?php

namespace App\Http\Resources;

use App\Models\Jugador;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Jugador */
class JugadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'posicion' => $this->posicion,
            'numero_camiseta' => $this->numero_camiseta,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'edad' => $this->fecha_nacimiento?->age,
            'nacionalidad' => $this->nacionalidad,
            'equipo' => $this->whenLoaded('equipos', fn () => ($club = $this->club()) ? [
                'id' => $club->id,
                'nombre' => $club->nombre,
                'nombre_corto' => $club->nombre_corto,
                'escudo' => $club->escudo,
            ] : null),
        ];
    }
}
