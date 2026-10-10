<?php

use App\Http\Middleware\ExigirUsuarioActivo;
use App\Http\Middleware\ForzarRespuestaJson;
use App\Soporte\Http\RenderizadorErroresApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // El enunciado pide las rutas exactamente como están escritas
        // (/competitions, /team, /players): la API no lleva prefijo "/api".
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->api(prepend: [ForzarRespuestaJson::class]);
        $middleware->alias(['activo' => ExigirUsuarioActivo::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(new RenderizadorErroresApi);
    })->create();
