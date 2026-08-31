<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Lightweight health and readiness endpoints for load balancers and
 * container orchestration.
 *
 * - GET /health/live  — process is alive (always 200 if PHP can respond)
 * - GET /health/ready — all dependencies (DB, Redis) are reachable
 *
 * These routes are exempt from auth, CSRF, and throttling to keep
 * health-check traffic cheap and reliable.
 */
final class HealthController extends Controller
{
    /**
     * Liveness probe — always returns 200 if PHP-FPM can serve the request.
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Readiness probe — checks DB and Redis connectivity.
     *
     * Returns 200 if all dependencies are healthy, 503 if any fail.
     * Individual check statuses are included in the response body.
     */
    public function ready(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        // Redis
        try {
            Redis::connection()->ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable $e) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
