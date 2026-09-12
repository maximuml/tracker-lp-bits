<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Circuit breaker for Redis access.
 *
 * A stopped Redis server costs ~5-10s per connection attempt (DNS/connect
 * retries). Without a breaker every request pays that stall at each call
 * site. attempt() runs a Redis-backed operation; on a connection failure it
 * marks Redis as down and returns the fallback. While marked down the flag
 * is shared across PHP-FPM workers through a small file, so all workers —
 * not just the one that observed the failure — fail fast for a short TTL.
 * This keeps the tracker answering announces from the database instead of
 * stalling behind a dead cache.
 *
 * Half-open probing: when the down flag expires, exactly one request becomes
 * the prober (a short probe window is written under an exclusive flock);
 * concurrent requests keep failing fast instead of stampeding Redis.
 */
final class RedisGuard
{
    private const DOWN_TTL_SECONDS = 30;

    private const PROBE_TTL_SECONDS = 15;

    private static ?float $downUntil = null;

    private static ?float $probingUntil = null;

    private function __construct() {}

    public static function available(): bool
    {
        $now = microtime(true);
        if (self::$probingUntil !== null && $now < self::$probingUntil) {
            return true;
        }
        if (self::$downUntil !== null) {
            if ($now < self::$downUntil) {
                return false;
            }
            self::$downUntil = null;
        }

        $flag = self::flagPath();
        if (! is_file($flag)) {
            return true;
        }

        // Read-extend under an exclusive lock so only one request probes.
        $fh = fopen($flag, 'c+');
        if ($fh === false) {
            return true;
        }
        flock($fh, LOCK_EX);
        $until = (float) (stream_get_contents($fh) ?: 0);
        if ($until > $now) {
            self::$downUntil = $until;
            flock($fh, LOCK_UN);
            fclose($fh);

            return false;
        }
        // Expired — claim the probe window for this request only.
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, (string) ($now + self::PROBE_TTL_SECONDS));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        self::$probingUntil = $now + self::PROBE_TTL_SECONDS;

        return true;
    }

    /**
     * Run a Redis-backed operation; on connection failure mark Redis down
     * and return $fallback. Only Redis connectivity exceptions are caught —
     * domain exceptions thrown inside $operation still propagate.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @param  T  $fallback
     * @return T
     */
    public static function attempt(callable $operation, mixed $fallback = null): mixed
    {
        if (! self::available()) {
            return $fallback;
        }

        try {
            $result = $operation();
        } catch (\RedisException|\RedisClusterException $e) {
            self::markDown();
            Log::warning('Redis unreachable; using fallback', ['error' => $e->getMessage()]);

            return $fallback;
        }

        if (self::$downUntil !== null || self::$probingUntil !== null) {
            self::reset();
        }

        return $result;
    }

    public static function markDown(): void
    {
        $until = microtime(true) + self::DOWN_TTL_SECONDS;
        self::$downUntil = $until;
        self::$probingUntil = null;
        @file_put_contents(self::flagPath(), (string) $until, LOCK_EX);
    }

    /** Clear cached down-state — used by tests and after a successful call. */
    public static function reset(): void
    {
        self::$downUntil = null;
        self::$probingUntil = null;
        if (is_file(self::flagPath())) {
            @unlink(self::flagPath());
        }
    }

    private static function flagPath(): string
    {
        return storage_path('framework/cache/redis-down');
    }
}
