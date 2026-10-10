<?php

namespace App\Aplicacion\Futbol;

use App\Dominio\Futbol\Datos\DatosLiga;
use DateTimeInterface;

/** Traduce DatosLiga (independiente del proveedor) a las columnas de la tabla ligas. */
final class AtributosLiga
{
    /** @return array<string, mixed> */
    public static function desde(DatosLiga $datos): array
    {
        return [
            'id' => $datos->id,
            'nombre' => $datos->nombre,
            'codigo' => $datos->codigo,
            'tipo' => $datos->tipo,
            'emblema' => $datos->emblema,
            'plan' => $datos->plan,
            'area_nombre' => $datos->areaNombre,
            'area_codigo' => $datos->areaCodigo,
            'area_bandera' => $datos->areaBandera,
            'temporada_inicio' => $datos->temporadaInicio?->format('Y-m-d'),
            'temporada_fin' => $datos->temporadaFin?->format('Y-m-d'),
            'jornada_actual' => $datos->jornadaActual,
            'actualizado_proveedor_en' => $datos->actualizadoEn?->format(DateTimeInterface::ATOM),
        ];
    }
}
