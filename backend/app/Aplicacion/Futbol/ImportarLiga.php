<?php

namespace App\Aplicacion\Futbol;

use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Eventos\LigaImportada;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Models\Liga;
use App\Models\LigaEquipo;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final class ImportarLiga
{
    public function __construct(
        private readonly ProveedorDatosFutbol $proveedor,
        private readonly GuardarPlanteles $guardarPlanteles,
        private readonly Dispatcher $eventos,
    ) {}

    public function ejecutar(int|string $referencia, string $origen = 'peticion'): Liga
    {
        $planteles = $this->proveedor->plantelesDeLiga($referencia);

        $liga = DB::transaction(function () use ($planteles) {
            $liga = $this->guardarLiga($planteles->liga);
            $this->guardarPlanteles->ejecutar($planteles);
            $this->vincularEquipos($liga, $planteles);

            return $liga;
        });

        $this->eventos->dispatch(new LigaImportada($liga->id, count($planteles->equipos), $planteles->cantidadJugadores(), $origen));

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

    /** upsert y no sync(): sync() borra e inserta, y dos importaciones simultáneas chocaban en la PK. */
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
