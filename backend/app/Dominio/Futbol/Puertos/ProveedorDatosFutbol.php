<?php

namespace App\Dominio\Futbol\Puertos;

use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;

/**
 * Puerto hacia la fuente externa de datos de fútbol. La aplicación solo
 * depende de este contrato; football-data.org es un adaptador detrás de él.
 *
 * @throws LimiteDePeticionesAlcanzado cuando no queda presupuesto de peticiones
 * @throws ProveedorNoDisponible cuando el proveedor no responde o falla
 * @throws RecursoNoEncontradoEnProveedor cuando la liga no existe
 * @throws LigaFueraDelPlan cuando nuestro plan no puede leer la liga
 */
interface ProveedorDatosFutbol
{
    /** @return list<DatosLiga> */
    public function ligas(): array;

    /** Liga (por id o código) con sus equipos actuales y sus planteles. */
    public function plantelesDeLiga(int|string $liga): PlantelesLiga;
}
