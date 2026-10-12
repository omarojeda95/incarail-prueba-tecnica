<?php

namespace App\Aplicacion\Futbol;

use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\DatosJugador;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Models\Equipo;
use App\Models\EquipoJugador;
use App\Models\Jugador;
use DateTimeInterface;

/**
 * Guarda los equipos, los jugadores y los planteles (equipo_jugador) que trae una importación.
 *
 * Reglas:
 *  - Toda escritura es un upsert por el id de football-data, con las filas ordenadas por id para que
 *    dos importaciones simultáneas las bloqueen en el mismo orden (sin deadlocks).
 *  - Una competición de selecciones (Mundial, Eurocopa) guarda sus equipos como 'seleccion' y no pisa
 *    los datos de un jugador que ya trajo su club.
 *  - Una liga trae el plantel completo de cada club: quien ya no viene sale de ese plantel. Las copas
 *    solo agregan, porque football-data envía sus planteles incompletos o vacíos.
 */
final class GuardarPlanteles
{
    public function ejecutar(PlantelesLiga $planteles): void
    {
        $esDeSelecciones = in_array($planteles->liga->codigo, config('futbol.competiciones_selecciones'), true);

        $this->guardarEquipos($planteles->equipos, $esDeSelecciones ? 'seleccion' : 'club');
        $this->guardarJugadores($planteles->equipos, soloNuevos: $esDeSelecciones);
        $this->guardarPertenencias($planteles->equipos);

        if ($planteles->liga->tipo === 'LEAGUE' && ! $esDeSelecciones) {
            $this->sacarDeLosPlantelesAQuienYaNoViene($planteles->equipos);
        }
    }

    /** @param  list<DatosEquipo>  $equipos */
    private function guardarEquipos(array $equipos, string $tipo): void
    {
        $filas = $this->unicasPorId(array_map(fn (DatosEquipo $equipo) => [
            'id' => $equipo->id,
            'nombre' => $equipo->nombre,
            'tipo' => $tipo,
            'nombre_corto' => $equipo->nombreCorto,
            'sigla' => $equipo->sigla,
            'escudo' => $equipo->escudo,
            'direccion' => $equipo->direccion,
            'sitio_web' => $equipo->sitioWeb,
            'fundacion' => $equipo->fundacion,
            'colores' => $equipo->colores,
            'estadio' => $equipo->estadio,
            'area_nombre' => $equipo->areaNombre,
            'entrenador' => $equipo->entrenador,
            'actualizado_proveedor_en' => $equipo->actualizadoEn?->format(DateTimeInterface::ATOM),
        ], $equipos));

        if ($filas !== []) {
            Equipo::query()->upsert($filas, ['id'], array_keys(array_diff_key($filas[0], ['id' => true])));
        }
    }

    /**
     * @param  list<DatosEquipo>  $equipos
     * @param  bool  $soloNuevos  true en competiciones de selecciones: no pisa a los jugadores ya guardados
     */
    private function guardarJugadores(array $equipos, bool $soloNuevos): void
    {
        $filas = [];
        foreach ($equipos as $equipo) {
            foreach ($equipo->plantel as $jugador) {
                $filas[] = [
                    'id' => $jugador->id,
                    'nombre' => $jugador->nombre,
                    'posicion' => $jugador->posicion,
                    'numero_camiseta' => $jugador->numeroCamiseta,
                    'fecha_nacimiento' => $jugador->fechaNacimiento?->format('Y-m-d'),
                    'nacionalidad' => $jugador->nacionalidad,
                ];
            }
        }

        // Un jugador puede venir en dos planteles (traspaso o préstamo); un upsert de PostgreSQL
        // no admite la misma clave dos veces, así que gana la última aparición.
        $filas = $this->unicasPorId($filas);

        if ($soloNuevos) {
            $guardados = Jugador::query()->whereKey(array_column($filas, 'id'))->pluck('id')->flip();
            $filas = array_values(array_filter($filas, fn (array $fila) => ! $guardados->has($fila['id'])));
        }

        foreach (array_chunk($filas, 500) as $lote) {
            Jugador::query()->upsert($lote, ['id'], array_keys(array_diff_key($lote[0], ['id' => true])));
        }
    }

    /** @param  list<DatosEquipo>  $equipos */
    private function guardarPertenencias(array $equipos): void
    {
        $filas = [];
        foreach ($equipos as $equipo) {
            foreach ($equipo->plantel as $jugador) {
                $filas["{$equipo->id}-{$jugador->id}"] = ['equipo_id' => $equipo->id, 'jugador_id' => $jugador->id];
            }
        }
        ksort($filas, SORT_NATURAL);

        foreach (array_chunk(array_values($filas), 500) as $lote) {
            EquipoJugador::query()->upsert($lote, ['equipo_id', 'jugador_id'], ['actualizado_en']);
        }
    }

    /** @param  list<DatosEquipo>  $equipos */
    private function sacarDeLosPlantelesAQuienYaNoViene(array $equipos): void
    {
        foreach ($equipos as $equipo) {
            if ($equipo->plantel !== []) {
                EquipoJugador::query()
                    ->where('equipo_id', $equipo->id)
                    ->whereNotIn('jugador_id', array_map(fn (DatosJugador $jugador) => $jugador->id, $equipo->plantel))
                    ->delete();
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>> sin ids repetidos (gana la última) y ordenadas por id
     */
    private function unicasPorId(array $filas): array
    {
        $filas = array_values(array_column($filas, null, 'id'));
        usort($filas, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        return $filas;
    }
}
