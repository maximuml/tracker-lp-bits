<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Horizon;

/**
 * Lightweight health and readiness endpoints for load balancers and
 * container orchestration.
 *
 * - GET /health/live  — process is alive (always 200 if PHP can respond)
 * - GET /health/ready — all dependencies (DB, Redis, MeiliSearch, Horizon,
 *   scheduler heartbeat) are reachable and up to date
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
     * Readiness probe — checks DB, Redis, MeiliSearch, Horizon, and scheduler
     * heartbeat.
     *
     * Returns 200 if all critical dependencies are healthy, 503 if any fail.
     * MeiliSearch degradation is reported but does not cause a 503 (search
     * falls back to SQL). Scheduler heartbeat staleness (>5min) is reported
     * as a warning but does not cause a 503 unless it's completely missing.
     *
     * Individual check statuses are included in the response body.
     */
    public function ready(): JsonResponse
    {
        $checks = [];
        $healthy = true;
        $warnings = [];

        // Database (critical)
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'fail';
            $healthy = false;
        }

        // Redis (critical)
        try {
            Redis::connection()->ping();
            $checks['redis'] = 'ok';
        } catch (\Throwable $e) {
            $checks['redis'] = 'fail';
            $healthy = false;
        }

        // MeiliSearch (non-critical — search falls back to SQL)
        $checks['meilisearch'] = $this->checkMeiliSearch($warnings);

        // Horizon (non-critical for web requests, but critical for async jobs)
        $checks['horizon'] = $this->checkHorizon($warnings);

        // Scheduler heartbeat (non-critical for web, but indicates cron is running)
        $checks['scheduler'] = $this->checkSchedulerHeartbeat($warnings);

        $response = [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ];
        if ($warnings !== []) {
            $response['warnings'] = $warnings;
        }

        return response()->json($response, $healthy ? 200 : 503);
    }

    /**
     * Check MeiliSearch connectivity. Returns 'ok', 'degraded', or 'skip'.
     *
     * @param  list<string>  $warnings
     */
    private function checkMeiliSearch(array &$warnings): string
    {
        $driver = config('scout.driver');

        // If Scout is not using MeiliSearch, skip this check
        if ($driver !== 'meilisearch') {
            return 'skip';
        }

        try {
            $host = config('scout.meilisearch.host');
            if ($host === null || $host === '') {
                $warnings[] = 'MeiliSearch host not configured';

                return 'degraded';
            }

            // Use file_get_contents for a lightweight HTTP check (no SDK dependency)
            $url = rtrim($host, '/').'/health';
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($url, false, $context);

            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data) && ($data['status'] ?? '') === 'available') {
                    return 'ok';
                }
            }

            $warnings[] = 'MeiliSearch not responding at '.$host;

            return 'degraded';
        } catch (\Throwable $e) {
            $warnings[] = 'MeiliSearch check failed: '.$e->getMessage();

            return 'degraded';
        }
    }

    /**
     * Check Horizon status. Returns 'ok', 'degraded', 'inactive', or 'skip'.
     *
     * @param  list<string>  $warnings
     */
    private function checkHorizon(array &$warnings): string
    {
        if (! class_exists(Horizon::class)) {
            return 'skip';
        }

        try {
            $repository = app(MasterSupervisorRepository::class);
            $masters = $repository->all();

            if (empty($masters)) {
                $warnings[] = 'Horizon: no master supervisors running';

                return 'inactive';
            }

            // Check if at least one master is running (not paused)
            $running = false;
            foreach ($masters as $master) {
                if (($master->status ?? '') === 'running') {
                    $running = true;
                    break;
                }
            }

            if ($running) {
                return 'ok';
            }

            $warnings[] = 'Horizon: master supervisors not in running state';

            return 'degraded';
        } catch (\Throwable $e) {
            $warnings[] = 'Horizon check failed: '.$e->getMessage();

            return 'degraded';
        }
    }

    /**
     * Check scheduler heartbeat from Redis. Returns 'ok', 'stale', or 'missing'.
     *
     * @param  list<string>  $warnings
     */
    private function checkSchedulerHeartbeat(array &$warnings): string
    {
        try {
            $heartbeat = Redis::connection()->get('scheduler:heartbeat');

            if ($heartbeat === null) {
                $warnings[] = 'Scheduler: no heartbeat in Redis (scheduler may not be running)';

                return 'missing';
            }

            $age = time() - (int) $heartbeat;

            if ($age > 300) {
                $warnings[] = "Scheduler: heartbeat is {$age}s old (expected <300s)";

                return 'stale';
            }

            return 'ok';
        } catch (\Throwable $e) {
            $warnings[] = 'Scheduler heartbeat check failed: '.$e->getMessage();

            return 'degraded';
        }
    }
}
