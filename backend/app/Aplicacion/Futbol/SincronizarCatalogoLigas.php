<?php

namespace App\Aplicacion\Futbol;

use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Models\Liga;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class SincronizarCatalogoLigas
{
    private const CLAVE_SINCRONIZADO_EN = 'futbol:catalogo:sincronizado-en';

    public function __construct(private readonly ProveedorDatosFutbol $proveedor) {}

    public function ejecutar(): int
    {
        $filas = array_map(AtributosLiga::desde(...), $this->proveedor->ligas());

        if ($filas !== []) {
            // equipos_sincronizados_en no se toca a propósito: es de la importación.
            Liga::query()->upsert($filas, ['id'], array_keys(array_diff_key($filas[0], ['id' => true])));
        }

        Cache::forever(self::CLAVE_SINCRONIZADO_EN, now()->getTimestamp());

        return count($filas);
    }

    public function ultimaSincronizacion(): ?Carbon
    {
        $marca = Cache::get(self::CLAVE_SINCRONIZADO_EN);

        return $marca ? Carbon::createFromTimestamp($marca) : null;
    }

    public function estaVencido(): bool
    {
        $sincronizadoEn = $this->ultimaSincronizacion();

        return $sincronizadoEn === null
            || $sincronizadoEn->lt(now()->subMinutes(config('futbol.vigencia.catalogo_minutos')))
            || ! Liga::query()->exists();
    }

    public static function marcarSincronizado(): void
    {
        Cache::forever(self::CLAVE_SINCRONIZADO_EN, now()->getTimestamp());
    }
}
