<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\LegacyScriptContext;
use App\Http\LegacyUrlRewriter;
use App\Support\Network;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureLegacyRouting();

        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api/v1')
                ->middleware(['api', 'locale'])
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware(['web', 'locale'])
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->namespace($this->namespace)
                ->middleware('throttle:third-party')
                ->group(base_path('routes/third-party.php'));

            Route::middleware('throttle:tracker')
                ->namespace($this->namespace)
                ->group(base_path('routes/tracker.php'));

        });
    }

    /**
     * Register the legacy URL rewriter and route parameter patterns.
     *
     * The LegacyUrlRewriter handles per-request rewriting of legacy .php URLs
     * to canonical Laravel routes (e.g. /details.php?id=1 → /details/1).
     * Route::pattern declarations enforce numeric constraints on legacy route
     * parameters so that malformed IDs are rejected before reaching controllers.
     */
    protected function configureLegacyRouting(): void
    {
        $this->app->singleton(LegacyUrlRewriter::class);
        $this->app->singleton(LegacyScriptContext::class);

        Route::pattern('id', '[0-9]+');
        Route::pattern('commentId', '[0-9]+');
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('third-party', function (Request $request) {
            return Limit::perMinute(10)->by(Network::clientIp());
        });

        // Stricter rate limit for third-party auth endpoints (approve/challenge).
        // These are high-risk targets for credential brute-forcing.
        RateLimiter::for('third-party-auth', function (Request $request) {
            return Limit::perMinute(5)->by(Network::clientIp());
        });

        RateLimiter::for('tracker', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip() ?? 'default');
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'default');
        });

        RateLimiter::for('ajax', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'default');
        });

        RateLimiter::for('shoutbox', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('comment', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('attachment', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('torrents', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'default');
        });

        RateLimiter::for('download', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('legacy', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip() ?? 'default');
        });

        RateLimiter::for('passkey-login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'default');
        });
    }
}
