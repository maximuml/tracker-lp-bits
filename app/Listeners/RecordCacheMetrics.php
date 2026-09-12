<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\RedisGuard;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Support\Facades\Redis;

/**
 * T-23: Records cache hit/miss events to Redis for /metrics.
 */
final class RecordCacheMetrics
{
    public function handleHit(CacheHit $event): void
    {
        $this->increment('metrics:cache_hits');
    }

    public function handleMiss(CacheMissed $event): void
    {
        $this->increment('metrics:cache_misses');
    }

    private function increment(string $key): void
    {
        // Best-effort — the breaker keeps a dead Redis from stalling callers.
        RedisGuard::attempt(static fn () => Redis::connection()->incr($key));
    }
}
