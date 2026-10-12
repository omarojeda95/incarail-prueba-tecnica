<?php

namespace App\Providers;

use App\Dominio\Futbol\Puertos\ColaDeImportaciones;
use App\Dominio\Futbol\Puertos\ProveedorDatosFutbol;
use App\Infraestructura\Colas\ColaDeImportacionesLaravel;
use App\Infraestructura\FootballData\ClienteFootballData;
use App\Infraestructura\FootballData\PresupuestoPeticiones;
use App\Infraestructura\FootballData\PresupuestoVentanaDeslizante;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PresupuestoPeticiones::class, fn () => new PresupuestoVentanaDeslizante(
            Cache::store(config('futbol.limite_peticiones.almacen_cache')),
            maximo: config('futbol.limite_peticiones.maximo'),
            ventanaSegundos: config('futbol.limite_peticiones.ventana_segundos'),
        ));

        $this->app->bind(ClienteFootballData::class, fn ($app) => new ClienteFootballData(
            $app->make(HttpFactory::class),
            $app->make(PresupuestoPeticiones::class),
            token: config('futbol.token'),
            urlBase: config('futbol.url_base'),
            tiempoEspera: config('futbol.tiempo_espera'),
        ));

        $this->app->bind(ProveedorDatosFutbol::class, ClienteFootballData::class);
        $this->app->bind(ColaDeImportaciones::class, ColaDeImportacionesLaravel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addMinutes(config('passport.minutos_vida_token')));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('inicio-sesion', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input('correo')).'|'.$request->ip()
        ));
    }
}
