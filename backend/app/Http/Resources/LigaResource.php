<?php

namespace App\Http\Resources;

use App\Models\Liga;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Liga */
class LigaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'emblema' => $this->emblema,
            'plan' => $this->plan,
            'disponible' => $this->esImportable(),
            'area' => [
                'nombre' => $this->area_nombre,
                'codigo' => $this->area_codigo,
                'bandera' => $this->area_bandera,
            ],
            'temporada' => [
                'inicio' => $this->temporada_inicio?->toDateString(),
                'fin' => $this->temporada_fin?->toDateString(),
                'jornada_actual' => $this->jornada_actual,
            ],
            'importada' => $this->estaImportada(),
            'equipos_sincronizados_en' => $this->equipos_sincronizados_en?->toIso8601String(),
            'cantidad_equipos' => $this->whenHas('cantidad_equipos'),
            'equipos' => EquipoResource::collection($this->whenLoaded('equipos')),
            'ultima_importacion' => $this->whenLoaded('ultimaImportacion', fn () => $this->ultimaImportacion ? [
                'cantidad_equipos' => $this->ultimaImportacion->cantidad_equipos,
                'cantidad_jugadores' => $this->ultimaImportacion->cantidad_jugadores,
                'origen' => $this->ultimaImportacion->origen,
                'importado_en' => $this->ultimaImportacion->importado_en->toIso8601String(),
            ] : null),
        ];
    }
}
