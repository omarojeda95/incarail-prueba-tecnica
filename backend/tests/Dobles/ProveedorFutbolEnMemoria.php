<?php

namespace Tests\Dobles;

use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\DatosJugador;
use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use DateTimeImmutable;
use Throwable;

/** Segundo adaptador del puerto: permite probar los casos de uso sin HTTP. */
final class ProveedorFutbolEnMemoria implements ProveedorDatosFutbol
{
    /** @var array<int|string, PlantelesLiga> */
    private array $planteles = [];

    /** @var list<DatosLiga> */
    private array $ligas = [];

    private ?Throwable $falla = null;

    public int $llamadas = 0;

    public function ligas(): array
    {
        $this->registrarLlamada();

        return $this->ligas;
    }

    public function plantelesDeLiga(int|string $liga): PlantelesLiga
    {
        $this->registrarLlamada();

        return $this->planteles[$liga] ?? throw new RecursoNoEncontradoEnProveedor("La liga {$liga} no existe.");
    }

    public function conLigas(DatosLiga ...$ligas): self
    {
        $this->ligas = $ligas;

        return $this;
    }

    public function conPlanteles(PlantelesLiga $planteles): self
    {
        $this->planteles[$planteles->liga->id] = $planteles;
        $this->planteles[$planteles->liga->codigo] = $planteles;

        return $this;
    }

    public function fallarCon(?Throwable $falla): self
    {
        $this->falla = $falla;

        return $this;
    }

    private function registrarLlamada(): void
    {
        $this->llamadas++;

        if ($this->falla) {
            throw $this->falla;
        }
    }

    public static function liga(int $id = 2021, string $codigo = 'PL', string $plan = 'TIER_ONE', string $tipo = 'LEAGUE'): DatosLiga
    {
        return new DatosLiga(
            id: $id, nombre: "Liga {$codigo}", codigo: $codigo, tipo: $tipo, emblema: null, plan: $plan,
            areaNombre: 'England', areaCodigo: 'ENG', areaBandera: null,
            temporadaInicio: new DateTimeImmutable('2026-08-15'), temporadaFin: new DateTimeImmutable('2027-05-23'),
            jornadaActual: 7, actualizadoEn: new DateTimeImmutable('2026-10-01'),
        );
    }

    /** @param  list<DatosJugador>  $plantel */
    public static function equipo(int $id, string $nombre, array $plantel = []): DatosEquipo
    {
        return new DatosEquipo(
            id: $id, nombre: $nombre, nombreCorto: $nombre, sigla: strtoupper(substr($nombre, 0, 3)), escudo: null,
            direccion: null, sitioWeb: null, fundacion: 1900, colores: null, estadio: null, areaNombre: 'England',
            entrenador: null, actualizadoEn: null, plantel: $plantel,
        );
    }

    public static function jugador(int $id, string $nombre, ?string $posicion = 'Midfield', ?int $camiseta = null): DatosJugador
    {
        return new DatosJugador(
            id: $id, nombre: $nombre, posicion: $posicion, numeroCamiseta: $camiseta,
            fechaNacimiento: new DateTimeImmutable('2000-01-01'), nacionalidad: 'England',
        );
    }
}
