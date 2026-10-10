<?php

namespace App\Http\Resources;

use App\Models\Equipo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Equipo */
class EquipoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'nombre_corto' => $this->nombre_corto,
            'sigla' => $this->sigla,
            'escudo' => $this->escudo,
            'direccion' => $this->direccion,
            'sitio_web' => $this->sitio_web,
            'fundacion' => $this->fundacion,
            'colores' => $this->colores,
            'estadio' => $this->estadio,
            'area' => $this->area_nombre,
            'entrenador' => $this->entrenador,
            'cantidad_jugadores' => $this->whenCounted('jugadores'),
            'ligas' => $this->whenLoaded('ligas', fn () => $this->ligas->map(fn ($liga) => [
                'id' => $liga->id,
                'nombre' => $liga->nombre,
                'codigo' => $liga->codigo,
                'emblema' => $liga->emblema,
                'temporada' => $liga->pivot?->temporada,
            ])),
            'jugadores' => JugadorResource::collection($this->whenLoaded('jugadores')),
        ];
    }
}
