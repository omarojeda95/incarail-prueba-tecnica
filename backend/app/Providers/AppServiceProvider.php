<?php

namespace App\Providers;

use App\Domain\Football\Ports\FootballDataProvider;
use App\Infrastructure\FootballData\FootballDataClient;
use App\Infrastructure\FootballData\RequestBudget;
use App\Infrastructure\FootballData\SlidingWindowRequestBudget;
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
        $this->app->singleton(RequestBudget::class, fn () => new SlidingWindowRequestBudget(
            Cache::store(config('football.rate_limit.cache_store')),
            maxRequests: config('football.rate_limit.max_requests'),
            windowSeconds: config('football.rate_limit.window_seconds'),
        ));

        $this->app->bind(FootballDataClient::class, fn ($app) => new FootballDataClient(
            $app->make(HttpFactory::class),
            $app->make(RequestBudget::class),
            token: config('football.token'),
            baseUrl: config('football.base_url'),
            timeout: config('football.timeout'),
        ));

        $this->app->bind(FootballDataProvider::class, FootballDataClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addMinutes(config('passport.token_ttl_minutes')));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            strtolower((string) $request->input('email')).'|'.$request->ip()
        ));
    }
}
