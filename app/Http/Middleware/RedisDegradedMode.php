<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Degrades request-scoped drivers when Redis is unreachable.
 *
 * Sessions, the Laravel cache store and the queue connection all default
 * to Redis; with Redis down every one of them throws/stalls before any
 * per-call-site RedisGuard wrapper can run (StartSession precedes
 * controllers). This middleware runs before StartSession: a cheap PING
 * through RedisGuard::attempt() keeps the shared down-flag fresh, and
 * while flagged the request uses file sessions, the file cache and the
 * sync queue. Cookie auth (c_secure_pass) does not depend on the session
 * store, so authenticated pages keep rendering; only session-bound state
 * (flash data, CSRF tokens issued in the same degraded window) degrades.
 */
final class RedisDegradedMode
{
    public function handle(Request $request, Closure $next): mixed
    {
        $pinged = RedisGuard::attempt(static fn () => (bool) Redis::connection()->ping(), false);
        $degraded = ! $pinged || ! RedisGuard::available();

        $original = null;
        if ($degraded) {
            $original = [
                'session.driver' => config('session.driver'),
                'cache.default' => config('cache.default'),
                'queue.default' => config('queue.default'),
            ];
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);
        }

        try {
            return $next($request);
        } finally {
            // Restore so an Octane worker does not leak the degraded
            // drivers into subsequent requests.
            if ($original !== null) {
                config($original);
            }
        }
    }
}
