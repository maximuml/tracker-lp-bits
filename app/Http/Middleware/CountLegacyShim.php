<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Count hits on the legacy-URI redirect shims (routes/legacy/*.php plus the
 * stray dispatcher routes in web.php) so each shim can be retired once its
 * counter goes quiet — the same soak-then-delete gate as /ajax.
 *
 * Only redirect responses are counted, keyed by route URI pattern
 * ('details/{id}' collapses all ids into one label). Canonical endpoints
 * that share a group — the /comment/* routes — return views and never
 * increment. Exposed on /metrics as
 * `nexus_legacy_shim_hits_total{uri="...",status="..."}`.
 *
 * Failures (e.g. Redis unavailable) are silently ignored.
 */
final class CountLegacyShim
{
    private const COUNTED_STATUSES = [301, 302, 307, 308];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $status = $response->getStatusCode();
        $uri = $request->route()?->uri();

        if (in_array($status, self::COUNTED_STATUSES, true) && is_string($uri) && $uri !== '') {
            RedisGuard::attempt(static function () use ($uri, $status) {
                $redis = Redis::connection();
                $redis->incr("metrics:legacy_shim:{$uri}:{$status}");
                $redis->sadd('metrics:legacy_shim_keys', "{$uri}:{$status}");
            });
        }

        return $response;
    }
}
