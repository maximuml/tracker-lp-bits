<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record HTTP request metrics (status code counter) to Redis.
 *
 * Increments a Redis counter for each response status code, enabling
 * the /metrics endpoint to expose `nexus_http_requests_total{status="..."}`
 * in Prometheus format.
 *
 * Failures (e.g. Redis unavailable) are silently ignored to avoid
 * impacting request handling.
 */
final class RecordHttpMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip metrics endpoint itself to avoid self-counting
        if ($request->path() === 'metrics') {
            return $response;
        }

        $status = (string) $response->getStatusCode();

        try {
            Redis::connection()->incr("metrics:http_requests:{$status}");
        } catch (\Throwable) {
            // Non-critical: metrics are best-effort
        }

        return $response;
    }
}
