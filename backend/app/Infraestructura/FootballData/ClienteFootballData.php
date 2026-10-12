<?php

namespace App\Infraestructura\FootballData;

use App\Dominio\Futbol\Datos\DatosEquipo;
use App\Dominio\Futbol\Datos\DatosJugador;
use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/** Única clase que habla con football-data. */
final class ClienteFootballData implements ProveedorDatosFutbol
{
    private const SEGUNDOS_REINICIO_POR_DEFECTO = 60;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly PresupuestoPeticiones $presupuesto,
        private readonly ?string $token,
        private readonly string $urlBase,
        private readonly int $tiempoEspera,
    ) {}

    public function ligas(): array
    {
        $respuesta = $this->obtener('competitions');

        return array_map($this->mapearLiga(...), $respuesta['competitions'] ?? []);
    }

    public function plantelesDeLiga(int|string $liga): PlantelesLiga
    {
        $respuesta = $this->obtener('competitions/'.rawurlencode((string) $liga).'/teams');
        $temporada = $respuesta['season'] ?? [];

        return new PlantelesLiga(
            liga: $this->mapearLiga([...$respuesta['competition'], 'currentSeason' => $temporada]),
            temporada: isset($temporada['startDate']) ? substr($temporada['startDate'], 0, 4) : null,
            equipos: array_map($this->mapearEquipo(...), $respuesta['teams'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    private function obtener(string $ruta): array
    {
        if (blank($this->token)) {
            throw ProveedorNoDisponible::sinConfigurar();
        }

        $this->presupuesto->reservar();

        try {
            $respuesta = $this->http
                ->baseUrl($this->urlBase)
                ->withHeaders(['X-Auth-Token' => $this->token])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->tiempoEspera)
                ->get($ruta);
        } catch (ConnectionException $e) {
            Log::warning('football-data inalcanzable', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            throw new ProveedorNoDisponible('No se pudo conectar con football-data.org.', sinConexion: true, anterior: $e);
        }

        $this->sincronizarPresupuesto($respuesta);

        return $this->contenidoDe($respuesta, $ruta);
    }

    /** @return array<string, mixed> */
    private function contenidoDe(Response $respuesta, string $ruta): array
    {
        $mensaje = $respuesta->json('message') ?? $respuesta->reason();

        return match (true) {
            $respuesta->successful() && is_array($respuesta->json()) => $respuesta->json(),
            $respuesta->status() === 429 => throw new LimiteDePeticionesAlcanzado($this->segundosParaReinicio($respuesta)),
            $respuesta->status() === 403 => throw new LigaFueraDelPlan($mensaje),
            in_array($respuesta->status(), [400, 404], true) => throw new RecursoNoEncontradoEnProveedor($mensaje),
            default => throw $this->respuestaInesperada($respuesta, $ruta),
        };
    }

    private function respuestaInesperada(Response $respuesta, string $ruta): ProveedorNoDisponible
    {
        Log::error('football-data respondió algo inesperado', ['ruta' => $ruta, 'estado' => $respuesta->status()]);

        return new ProveedorNoDisponible("football-data.org respondió HTTP {$respuesta->status()}.");
    }

    /** Si football-data dice que no queda cupo, le creemos más que a nuestro contador. */
    private function sincronizarPresupuesto(Response $respuesta): void
    {
        $disponibles = $respuesta->header('X-Requests-Available-Minute');

        if ($respuesta->status() === 429 || ($disponibles !== '' && (int) $disponibles <= 0)) {
            $this->presupuesto->agotarPor($this->segundosParaReinicio($respuesta));
        }
    }

    private function segundosParaReinicio(Response $respuesta): int
    {
        $reinicio = (int) $respuesta->header('X-RequestCounter-Reset');

        return $reinicio > 0 ? $reinicio : self::SEGUNDOS_REINICIO_POR_DEFECTO;
    }

    /** @param  array<string, mixed>  $item */
    private function mapearLiga(array $item): DatosLiga
    {
        $temporada = $item['currentSeason'] ?? [];

        return new DatosLiga(
            id: (int) $item['id'],
            nombre: $item['name'],
            codigo: $item['code'] ?? null,
            tipo: $item['type'] ?? null,
            emblema: $item['emblem'] ?? null,
            plan: $item['plan'] ?? null,
            areaNombre: $item['area']['name'] ?? null,
            areaCodigo: $item['area']['code'] ?? null,
            areaBandera: $item['area']['flag'] ?? null,
            temporadaInicio: $this->fecha($temporada['startDate'] ?? null),
            temporadaFin: $this->fecha($temporada['endDate'] ?? null),
            jornadaActual: $temporada['currentMatchday'] ?? null,
            actualizadoEn: $this->fecha($item['lastUpdated'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $item */
    private function mapearEquipo(array $item): DatosEquipo
    {
        return new DatosEquipo(
            id: (int) $item['id'],
            nombre: $item['name'],
            nombreCorto: $item['shortName'] ?? null,
            sigla: $item['tla'] ?? null,
            escudo: $item['crest'] ?? null,
            direccion: $item['address'] ?? null,
            sitioWeb: $item['website'] ?? null,
            fundacion: $item['founded'] ?? null,
            colores: $item['clubColors'] ?? null,
            estadio: $item['venue'] ?? null,
            areaNombre: $item['area']['name'] ?? null,
            entrenador: $item['coach']['name'] ?? null,
            actualizadoEn: $this->fecha($item['lastUpdated'] ?? null),
            plantel: array_map($this->mapearJugador(...), $item['squad'] ?? []),
        );
    }

    /** @param  array<string, mixed>  $item */
    private function mapearJugador(array $item): DatosJugador
    {
        return new DatosJugador(
            id: (int) $item['id'],
            nombre: $item['name'],
            posicion: $item['position'] ?? null,
            // No todos los planes lo incluyen en el plantel: se guarda cuando viene.
            numeroCamiseta: isset($item['shirtNumber']) ? (int) $item['shirtNumber'] : null,
            fechaNacimiento: $this->fecha($item['dateOfBirth'] ?? null),
            nacionalidad: $item['nationality'] ?? null,
        );
    }

    private function fecha(?string $valor): ?DateTimeImmutable
    {
        return $valor ? new DateTimeImmutable($valor) : null;
    }
}
