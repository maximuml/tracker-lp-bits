<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisGuard;
use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Tracker rate limiting with a fail-open Redis breaker.
 *
 * The announce/scrape limiter state lives in the Redis cache store. When
 * Redis is unreachable the stock throttle would 500 every tracker request
 * before it reaches the controller. This variant marks Redis down (so other
 * call sites skip their probes too) and lets the request through — the
 * tracker must keep answering clients during a cache outage.
 */
class TrackerThrottle extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = '')
    {
        if (! RedisGuard::available()) {
            return $next($request);
        }

        try {
            // Forward the exact argument list — ThrottleRequests detects the
            // named-limiter form ('throttle.tracker:<name>') via
            // func_num_args() === 3, which padding defaults would break.
            return parent::handle(...func_get_args());
        } catch (\Throwable $e) {
            if (! $e instanceof \RedisException && ! $e instanceof \RedisClusterException) {
                throw $e;
            }
            RedisGuard::markDown();

            return $next($request);
        }
    }
}
