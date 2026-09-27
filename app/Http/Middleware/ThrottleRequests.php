<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisGuard;
use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottleRequests;

class ThrottleRequests extends BaseThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (config('nexus.rate_limiting') === false || ! RedisGuard::available()) {
            return $next($request);
        }

        // Forward the exact arguments: the parent distinguishes named
        // limiters (throttle:name → 3 args) from inline ones
        // (throttle:60,1 → 4 args) via func_num_args(). The RateLimiter
        // store is bound at boot to whatever cache.default was then, so a
        // mid-request degraded-mode swap cannot reach it — fail open on
        // connectivity errors instead of 500ing the page.
        try {
            return parent::handle(...func_get_args());
        } catch (\Throwable $e) {
            if (! RedisGuard::isConnectivityFailure($e)) {
                throw $e;
            }
            RedisGuard::markDown();

            return $next($request);
        }
    }
}
