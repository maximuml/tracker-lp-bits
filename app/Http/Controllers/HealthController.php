<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\UserDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
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
 * - GET /health/diag  — authenticated diagnostics for staff (sysop class)
 *
 * Readiness semantics (W6-03):
 * - database, redis: hard failures — 503 when unreachable
 * - meilisearch: reported as 'degraded' but never fails readiness —
 *   search falls back to SQL (see SearchService)
 * - horizon: 'inactive'/'degraded' when no running master supervisors —
 *   async jobs stall but the site still serves; reported, not fatal
 * - scheduler: 'missing'/'stale' (>300s heartbeat age) is reported as a
 *   warning — cleanup jobs lag but the site still serves
 * - All probes time out quickly (<=3s per check) so a hanging dependency
 *   cannot stall readiness indefinitely.
 *
 * /health/live and /health/ready are exempt from auth, CSRF, and
 * throttling to keep health-check traffic cheap and reliable; they never
 * expose exception messages. /health/diag requires an authenticated
 * sysop because it exposes infrastructure details.
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

            $data = $this->fetchMeiliHealth($host);

            if (is_array($data) && ($data['status'] ?? '') === 'available') {
                return 'ok';
            }

            $warnings[] = 'MeiliSearch not responding';

            return 'degraded';
        } catch (\Throwable $e) {
            logger()->warning('health.ready meilisearch check failed', ['error' => $e->getMessage()]);
            $warnings[] = 'MeiliSearch check failed';

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
            logger()->warning('health.ready horizon check failed', ['error' => $e->getMessage()]);
            $warnings[] = 'Horizon check failed';

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
            logger()->warning('health.ready scheduler check failed', ['error' => $e->getMessage()]);
            $warnings[] = 'Scheduler heartbeat check failed';

            return 'degraded';
        }
    }

    /**
     * Authenticated diagnostics for staff (sysop class required).
     *
     * Returns dependency latencies, scheduler heartbeat age, Horizon
     * master count, PHP/runtime info, and disk usage. Exception details
     * are logged server-side only.
     */
    public function diag(): JsonResponse
    {
        $sysopClass = defined('UC_SYSOP') ? (int) \constant('UC_SYSOP') : 15;
        if (UserDisplay::currentClass() < $sysopClass) {
            abort(403);
        }

        return response()->json([
            'status' => 'ok',
            'php' => PHP_VERSION,
            'laravel' => App::version(),
            'environment' => App::environment(),
            'db_ping_ms' => $this->measureMs(static fn () => DB::connection()->getPdo()),
            'redis_ping_ms' => $this->measureMs(static fn () => Redis::connection()->ping()),
            'meilisearch_ms' => $this->measureMs(fn () => $this->probeMeiliSearch()),
            'scheduler_heartbeat_age' => $this->schedulerHeartbeatAge(),
            'horizon_masters' => $this->horizonMasterCount(),
            'disk_free_bytes' => @disk_free_space(base_path()) ?: null,
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'time' => time(),
        ]);
    }

    /**
     * Measure a dependency probe in milliseconds. Returns -1 on failure.
     *
     * @param  callable(): mixed  $probe
     */
    private function measureMs(callable $probe): float
    {
        $start = hrtime(true);
        try {
            $probe();
        } catch (\Throwable) {
            return -1;
        }

        return round((hrtime(true) - $start) / 1_000_000, 2);
    }

    private function probeMeiliSearch(): void
    {
        $host = config('scout.meilisearch.host');
        if (! is_string($host) || $host === '') {
            throw new \RuntimeException('unconfigured');
        }
        if ($this->fetchMeiliHealth($host) === null) {
            throw new \RuntimeException('unreachable');
        }
    }

    /**
     * Fetch MeiliSearch /health with a bounded connect+total timeout.
     * file_get_contents' http.timeout does not cover the TCP connect phase,
     * which can stall for seconds on an unreachable host — curl bounds both.
     *
     * @return array<string, mixed>|null decoded body, or null on failure
     */
    private function fetchMeiliHealth(string $host): ?array
    {
        $ch = curl_init(rtrim($host, '/').'/health');
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 1000,
            CURLOPT_TIMEOUT_MS => 3000,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (! is_string($response)) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }

    private function schedulerHeartbeatAge(): ?int
    {
        try {
            $heartbeat = Redis::connection()->get('scheduler:heartbeat');

            return $heartbeat === null ? null : time() - (int) $heartbeat;
        } catch (\Throwable) {
            return null;
        }
    }

    private function horizonMasterCount(): ?int
    {
        if (! class_exists(Horizon::class)) {
            return null;
        }
        try {
            return count((array) App::make(MasterSupervisorRepository::class)->all());
        } catch (\Throwable) {
            return null;
        }
    }
}
