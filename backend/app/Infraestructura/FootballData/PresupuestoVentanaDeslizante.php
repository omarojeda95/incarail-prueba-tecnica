<?php

namespace App\Infraestructura\FootballData;

use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use Illuminate\Contracts\Cache\Repository;

/**
 * Registro en ventana deslizante de las peticiones enviadas en los últimos N
 * segundos, guardado en una caché compartida (Redis en Docker) bajo un lock
 * atómico: los procesos web, el worker y el scheduler gastan el mismo cupo.
 *
 * Una ventana fija permitiría el doble del límite alrededor del cambio de
 * minuto (10 a las 00:59 + 10 a las 01:00); la ventana deslizante nunca
 * envía más de $maximo peticiones en ningún intervalo de $ventanaSegundos.
 */
final class PresupuestoVentanaDeslizante implements PresupuestoPeticiones
{
    public function __construct(
        private readonly Repository $cache,
        private readonly int $maximo,
        private readonly int $ventanaSegundos,
        private readonly string $clave = 'football-data:presupuesto',
    ) {}

    public function reservar(): void
    {
        $this->cache->lock("{$this->clave}:lock", 5)->block(5, function () {
            $espera = $this->calcularEspera($registros = $this->registrosRecientes());

            if ($espera > 0) {
                throw new LimiteDePeticionesAlcanzado($espera);
            }

            $registros[] = now()->getTimestampMs();
            $this->cache->put("{$this->clave}:registros", $registros, $this->ventanaSegundos + 5);
        });
    }

    public function restantes(): int
    {
        if ($this->segundosBloqueado() > 0) {
            return 0;
        }

        return max(0, $this->maximo - count($this->registrosRecientes()));
    }

    public function segundosParaReintentar(): int
    {
        return $this->calcularEspera($this->registrosRecientes());
    }

    public function agotarPor(int $segundos): void
    {
        $hasta = now()->addSeconds(max(1, $segundos))->getTimestamp();
        $this->cache->put("{$this->clave}:bloqueado-hasta", $hasta, max(1, $segundos) + 5);
    }

    /** @param  list<int>  $registros */
    private function calcularEspera(array $registros): int
    {
        $bloqueado = $this->segundosBloqueado();

        if (count($registros) < $this->maximo) {
            return $bloqueado;
        }

        $expiraElMasAntiguo = intdiv($registros[count($registros) - $this->maximo], 1000) + $this->ventanaSegundos;

        return max($bloqueado, $expiraElMasAntiguo - now()->getTimestamp(), 1);
    }

    private function segundosBloqueado(): int
    {
        $hasta = (int) $this->cache->get("{$this->clave}:bloqueado-hasta", 0);

        return max(0, $hasta - now()->getTimestamp());
    }

    /** @return list<int> marcas de tiempo (ms) de las peticiones dentro de la ventana */
    private function registrosRecientes(): array
    {
        $desde = now()->subSeconds($this->ventanaSegundos)->getTimestampMs();

        return array_values(array_filter(
            $this->cache->get("{$this->clave}:registros", []),
            fn (int $registro) => $registro > $desde,
        ));
    }
}
