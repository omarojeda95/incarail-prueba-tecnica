<?php

namespace App\Dominio\Futbol\Puertos;

use App\Dominio\Futbol\Datos\DatosLiga;
use App\Dominio\Futbol\Datos\PlantelesLiga;
use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;

/**
 * @throws LimiteDePeticionesAlcanzado cuando no queda presupuesto de peticiones
 * @throws ProveedorNoDisponible cuando el proveedor no responde o falla
 * @throws RecursoNoEncontradoEnProveedor cuando la liga no existe
 * @throws LigaFueraDelPlan cuando nuestro plan no puede leer la liga
 */
interface ProveedorDatosFutbol
{
    /** @return list<DatosLiga> */
    public function ligas(): array;

    public function plantelesDeLiga(int|string $liga): PlantelesLiga;
}
