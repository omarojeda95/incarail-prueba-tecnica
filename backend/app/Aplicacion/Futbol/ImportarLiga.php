<?php

namespace App\Aplicacion\Futbol;

use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Eventos\LigaImportada;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\LigaEquipo;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Comando: copia una liga, sus equipos y sus planteles a nuestra base.
 * Cuesta exactamente una petición a football-data. Toda escritura es un
 * upsert por el id de football-data: repetirla nunca duplica registros.
 */
final class ImportarLiga
{
    public function __construct(private readonly ProveedorDatosFutbol $proveedor) {}

    public function ejecutar(int|string $referencia, string $origen = 'peticion'): Liga
    {
        $planteles = $this->proveedor->plantelesDeLiga($referencia);

        $liga = DB::transaction(function () use ($planteles) {
            $liga = $this->guardarLiga($planteles->liga);
            $this->guardarEquiposYJugadores($planteles);
            $this->vincularEquipos($liga, $planteles);

            return $liga;
        });

        LigaImportada::dispatch($liga->id, count($planteles->equipos), $planteles->cantidadJugadores(), $origen);

        return $liga;
    }

    private function guardarLiga(DatosLiga $datos): Liga
    {
        $liga = Liga::query()->findOrNew($datos->id);

        // El endpoint de planteles envía una liga reducida (sin área ni plan):
        // nunca se pisa con nulos lo que ya guardó la sincronización del catálogo.
        $liga->fill(array_filter(AtributosLiga::desde($datos), fn ($valor) => $valor !== null));
        $liga->equipos_sincronizados_en = now();
        $liga->save();

        return $liga;
    }

    /**
     * Las filas se escriben ordenadas por id para que dos importaciones
     * concurrentes las bloqueen en el mismo orden (sin deadlocks).
     */
    private function guardarEquiposYJugadores(PlantelesLiga $planteles): void
    {
        $equipos = array_map(fn (DatosEquipo $equipo) => [
            'id' => $equipo->id,
            'nombre' => $equipo->nombre,
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
        ], $planteles->equipos);

        $jugadores = [];
        foreach ($planteles->equipos as $equipo) {
            foreach ($equipo->plantel as $jugador) {
                $jugadores[] = [
                    'id' => $jugador->id,
                    'equipo_id' => $equipo->id,
                    'nombre' => $jugador->nombre,
                    'posicion' => $jugador->posicion,
                    'numero_camiseta' => $jugador->numeroCamiseta,
                    'fecha_nacimiento' => $jugador->fechaNacimiento?->format('Y-m-d'),
                    'nacionalidad' => $jugador->nacionalidad,
                ];
            }
        }

        usort($equipos, fn (array $a, array $b) => $a['id'] <=> $b['id']);
        usort($jugadores, fn (array $a, array $b) => $a['id'] <=> $b['id']);

        if ($equipos !== []) {
            Equipo::query()->upsert($equipos, ['id'], array_keys(array_diff_key($equipos[0], ['id' => true])));
        }

        foreach (array_chunk($jugadores, 500) as $lote) {
            Jugador::query()->upsert($lote, ['id'], array_keys(array_diff_key($lote[0], ['id' => true])));
        }
    }

    /**
     * Upsert sobre la clave compuesta en vez de sync(): sync() lee, borra e
     * inserta, y dos importaciones simultáneas de la misma liga (una petición
     * y el job en cola) podían chocar con la clave primaria.
     */
    private function vincularEquipos(Liga $liga, PlantelesLiga $planteles): void
    {
        $idsEquipos = array_map(fn (DatosEquipo $equipo) => $equipo->id, $planteles->equipos);
        sort($idsEquipos);

        if ($idsEquipos !== []) {
            LigaEquipo::query()->upsert(
                array_map(fn (int $equipoId) => [
                    'liga_id' => $liga->id,
                    'equipo_id' => $equipoId,
                    'temporada' => $planteles->temporada,
                ], $idsEquipos),
                ['liga_id', 'equipo_id'],
                ['temporada'],
            );
        }

        // Los equipos que ya no están en la liga (descendidos) pierden el vínculo.
        LigaEquipo::query()
            ->where('liga_id', $liga->id)
            ->whereNotIn('equipo_id', $idsEquipos)
            ->delete();
    }
}
