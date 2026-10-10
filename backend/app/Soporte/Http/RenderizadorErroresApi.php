<?php

namespace App\Soporte\Http;

use App\Dominio\Acceso\Excepciones\CredencialesInvalidas;
use App\Dominio\Acceso\Excepciones\CuentaDesactivada;
use App\Dominio\Acceso\Excepciones\ReglaDeAccesoViolada;
use App\Dominio\Futbol\Excepciones\LigaFueraDelPlan;
use App\Dominio\Futbol\Excepciones\LimiteDePeticionesAlcanzado;
use App\Dominio\Futbol\Excepciones\ProveedorNoDisponible;
use App\Dominio\Futbol\Excepciones\RecursoNoEncontradoEnProveedor;
use App\Models\Equipo;
use App\Models\Jugador;
use App\Models\Liga;
use App\Models\Usuario;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Todos los errores salen de la API con el mismo formato:
 *   { "error": { "estado": 404, "codigo": "no_encontrado", "mensaje": "...", "detalles": {...} } }
 * "codigo" es estable y está pensado para programas; "mensaje" para personas.
 */
final class RenderizadorErroresApi
{
    private const MENSAJES_NO_ENCONTRADO = [
        Equipo::class => 'El equipo solicitado no existe.',
        Jugador::class => 'El jugador solicitado no existe.',
        Liga::class => 'La liga solicitada no existe.',
        Usuario::class => 'El usuario solicitado no existe.',
        Role::class => 'El rol solicitado no existe.',
    ];

    public function __invoke(Throwable $e): Response
    {
        // Laravel convierte ModelNotFound / Authorization en excepciones HTTP
        // antes de llamarnos; la original queda como "previous".
        $original = $e->getPrevious() ?? $e;

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof ValidationException => $this->error(422, 'validacion_fallida', 'Los datos enviados no son válidos.', ['campos' => $e->errors()]),
            $e instanceof CredencialesInvalidas => $this->error(401, 'credenciales_invalidas', 'Correo o contraseña incorrectos.'),
            $e instanceof AuthenticationException => $this->error(401, 'no_autenticado', 'Tu sesión no es válida o ha expirado. Inicia sesión nuevamente.'),
            $e instanceof CuentaDesactivada => $this->error(403, 'cuenta_desactivada', 'Tu cuenta está desactivada. Contacta a un administrador.'),
            $e instanceof UnauthorizedException, $e instanceof AccessDeniedHttpException => $this->error(403, 'sin_permiso', 'No tienes permiso para realizar esta acción.'),
            $original instanceof ModelNotFoundException => $this->error(404, 'no_encontrado', self::MENSAJES_NO_ENCONTRADO[$original->getModel()] ?? 'El recurso solicitado no existe.'),
            $e instanceof NotFoundHttpException => $this->error(404, 'no_encontrado', 'El recurso solicitado no existe.'),
            $e instanceof MethodNotAllowedHttpException => $this->error(405, 'metodo_no_permitido', 'Método HTTP no permitido para esta ruta.', encabezados: $e->getHeaders()),
            $e instanceof ThrottleRequestsException => $this->demasiadasSolicitudes($e),
            $e instanceof LimiteDePeticionesAlcanzado => $this->limiteFootballData($e),
            $e instanceof ProveedorNoDisponible => $this->proveedorNoDisponible($e),
            $e instanceof RecursoNoEncontradoEnProveedor => $this->error(404, 'liga_no_encontrada', 'La liga no existe en football-data.org.'),
            $e instanceof LigaFueraDelPlan => $this->error(403, 'liga_fuera_del_plan', 'Esta liga no está incluida en el plan gratuito de football-data.org.'),
            $e instanceof ReglaDeAccesoViolada => $this->error(409, 'conflicto', $e->getMessage()),
            $e instanceof HttpExceptionInterface => $this->error($e->getStatusCode(), 'error_http', $e->getMessage() ?: 'Error en la solicitud.', encabezados: $e->getHeaders()),
            default => $this->error(500, 'error_servidor', 'Ocurrió un error inesperado. Ya quedó registrado.', config('app.debug') ? [
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
                'traza' => collect($e->getTrace())->take(10)->map(fn ($marco) => ($marco['file'] ?? '?').':'.($marco['line'] ?? '?'))->all(),
            ] : []),
        };
    }

    private function demasiadasSolicitudes(ThrottleRequestsException $e): JsonResponse
    {
        $segundos = (int) ($e->getHeaders()['Retry-After'] ?? 60);

        return $this->error(429, 'demasiadas_solicitudes', "Demasiadas solicitudes. Intenta nuevamente en {$segundos} segundos.", ['reintentar_en' => $segundos], $e->getHeaders());
    }

    private function proveedorNoDisponible(ProveedorNoDisponible $e): JsonResponse
    {
        return match (true) {
            $e->sinConfigurar => $this->error(503, 'football_data_sin_configurar', 'La API no tiene configurado el token de football-data.org (FOOTBALL_DATA_TOKEN en .env).'),
            $e->sinConexion => $this->error(503, 'football_data_no_disponible', 'No pudimos conectarnos con football-data.org. Intenta nuevamente en unos minutos.'),
            default => $this->error(502, 'football_data_error', 'football-data.org respondió con un error. Intenta nuevamente en unos minutos.'),
        };
    }

    private function limiteFootballData(LimiteDePeticionesAlcanzado $e): JsonResponse
    {
        $mensaje = $e->enCola
            ? "football-data.org permite 10 consultas por minuto y ya se usaron. La importación quedó en cola y estará lista en unos {$e->reintentarEn} segundos."
            : "football-data.org permite 10 consultas por minuto y ya se usaron. Intenta nuevamente en {$e->reintentarEn} segundos.";

        return $this->error(429, 'football_data_limite_alcanzado', $mensaje, [
            'reintentar_en' => $e->reintentarEn,
            'en_cola' => $e->enCola,
        ], ['Retry-After' => (string) $e->reintentarEn]);
    }

    /**
     * @param  array<string, mixed>  $detalles
     * @param  array<string, string>  $encabezados
     */
    private function error(int $estado, string $codigo, string $mensaje, array $detalles = [], array $encabezados = []): JsonResponse
    {
        return new JsonResponse([
            'error' => array_filter([
                'estado' => $estado,
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'detalles' => $detalles ?: null,
            ], fn ($valor) => $valor !== null),
        ], $estado, $encabezados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
