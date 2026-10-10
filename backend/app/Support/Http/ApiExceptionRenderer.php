<?php

namespace App\Support\Http;

use App\Domain\Access\Exceptions\AccessRuleViolation;
use App\Domain\Access\Exceptions\AccountDisabled;
use App\Domain\Access\Exceptions\InvalidCredentials;
use App\Domain\Football\Exceptions\CompetitionNotAvailable;
use App\Domain\Football\Exceptions\FootballDataRateLimited;
use App\Domain\Football\Exceptions\FootballDataUnavailable;
use App\Domain\Football\Exceptions\FootballResourceNotFound;
use App\Models\Competition;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
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
 * Every error leaves the API with the same envelope:
 *   { "error": { "status": 404, "code": "not_found", "message": "...", "details": {...} } }
 * "code" is stable and meant for programs; "message" is meant for people.
 */
final class ApiExceptionRenderer
{
    private const MODEL_LABELS = [
        Team::class => 'El equipo solicitado no existe.',
        Player::class => 'El jugador solicitado no existe.',
        Competition::class => 'La liga solicitada no existe.',
        User::class => 'El usuario solicitado no existe.',
        Role::class => 'El rol solicitado no existe.',
    ];

    public function __invoke(Throwable $e): Response
    {
        // Laravel turns ModelNotFound / Authorization exceptions into HTTP ones
        // before calling us; the original is kept as "previous".
        $original = $e->getPrevious() ?? $e;

        return match (true) {
            $e instanceof HttpResponseException => $e->getResponse(),
            $e instanceof ValidationException => $this->error(422, 'validation_failed', 'Los datos enviados no son válidos.', ['fields' => $e->errors()]),
            $e instanceof InvalidCredentials => $this->error(401, 'invalid_credentials', 'Correo o contraseña incorrectos.'),
            $e instanceof AuthenticationException => $this->error(401, 'unauthenticated', 'Tu sesión no es válida o ha expirado. Inicia sesión nuevamente.'),
            $e instanceof AccountDisabled => $this->error(403, 'account_disabled', 'Tu cuenta está desactivada. Contacta a un administrador.'),
            $e instanceof UnauthorizedException, $e instanceof AccessDeniedHttpException => $this->error(403, 'forbidden', 'No tienes permiso para realizar esta acción.'),
            $original instanceof ModelNotFoundException => $this->error(404, 'not_found', self::MODEL_LABELS[$original->getModel()] ?? 'El recurso solicitado no existe.'),
            $e instanceof NotFoundHttpException => $this->error(404, 'not_found', 'El recurso solicitado no existe.'),
            $e instanceof MethodNotAllowedHttpException => $this->error(405, 'method_not_allowed', 'Método HTTP no permitido para esta ruta.', headers: $e->getHeaders()),
            $e instanceof ThrottleRequestsException => $this->tooManyRequests($e),
            $e instanceof FootballDataRateLimited => $this->footballRateLimited($e),
            $e instanceof FootballDataUnavailable => $e->connectionFailed
                ? $this->error(503, 'football_data_unavailable', 'No pudimos conectarnos con football-data.org. Intenta nuevamente en unos minutos.')
                : $this->error(502, 'football_data_error', 'football-data.org respondió con un error. Intenta nuevamente en unos minutos.'),
            $e instanceof FootballResourceNotFound => $this->error(404, 'competition_not_found', 'La liga no existe en football-data.org.'),
            $e instanceof CompetitionNotAvailable => $this->error(403, 'competition_not_in_plan', 'Esta liga no está incluida en el plan gratuito de football-data.org.'),
            $e instanceof AccessRuleViolation => $this->error(409, 'conflict', $e->getMessage()),
            $e instanceof HttpExceptionInterface => $this->error($e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Error en la solicitud.', headers: $e->getHeaders()),
            default => $this->error(500, 'server_error', 'Ocurrió un error inesperado. Ya quedó registrado.', config('app.debug') ? [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'trace' => collect($e->getTrace())->take(10)->map(fn ($frame) => ($frame['file'] ?? '?').':'.($frame['line'] ?? '?'))->all(),
            ] : []),
        };
    }

    private function tooManyRequests(ThrottleRequestsException $e): JsonResponse
    {
        $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 60);

        return $this->error(429, 'too_many_requests', "Demasiadas solicitudes. Intenta nuevamente en {$retryAfter} segundos.", ['retry_after' => $retryAfter], $e->getHeaders());
    }

    private function footballRateLimited(FootballDataRateLimited $e): JsonResponse
    {
        $message = $e->queued
            ? "football-data.org permite 10 consultas por minuto y ya se usaron. La importación quedó en cola y estará lista en unos {$e->retryAfter} segundos."
            : "football-data.org permite 10 consultas por minuto y ya se usaron. Intenta nuevamente en {$e->retryAfter} segundos.";

        return $this->error(429, 'football_data_rate_limited', $message, [
            'retry_after' => $e->retryAfter,
            'queued' => $e->queued,
        ], ['Retry-After' => (string) $e->retryAfter]);
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    private function error(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'error' => array_filter([
                'status' => $status,
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ], fn ($value) => $value !== null),
        ], $status, $headers);
    }
}
