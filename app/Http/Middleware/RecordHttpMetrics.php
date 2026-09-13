<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RedisGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Record HTTP request metrics (status code counter + latency) to Redis.
 *
 * Increments a Redis counter for each response status code and records
 * request duration in a latency histogram, enabling the /metrics
 * endpoint to expose:
 * - `nexus_http_requests_total{status="..."}`
 * - `nexus_http_request_duration_seconds_bucket{le="..."}`
 *
 * Failures (e.g. Redis unavailable) are silently ignored to avoid
 * impacting request handling.
 */
final class RecordHttpMetrics
{
    /**
     * Histogram bucket boundaries (seconds) for request latency.
     */
    private const LATENCY_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = hrtime(true);

        $response = $next($request);

        // Skip metrics endpoint itself to avoid self-counting
        if ($request->path() === 'metrics') {
            return $response;
        }

        $status = (string) $response->getStatusCode();
        $elapsed = (hrtime(true) - $startTime) / 1_000_000_000;

        RedisGuard::attempt(static function () use ($status, $elapsed) {
            $redis = Redis::connection();
            $redis->incr("metrics:http_requests:{$status}");
            $redis->sadd('metrics:http_statuses', $status);

            // Record latency in histogram buckets
            foreach (self::LATENCY_BUCKETS as $bucket) {
                if ($elapsed <= $bucket) {
                    $redis->incr("metrics:http_latency_bucket:{$bucket}");
                }
            }
            $redis->incr('metrics:http_latency_bucket:+Inf');
            $redis->incrByFloat('metrics:http_latency_sum', $elapsed);
            $redis->incr('metrics:http_latency_count');

            return null;
        });

        return $response;
    }
}
