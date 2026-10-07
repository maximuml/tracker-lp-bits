<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Support\RedisGuard;
use Illuminate\Support\Facades\Redis;

class LegacyRedisCache
{
    public bool $isEnabled = false;

    public int $clearCache = 0;

    public int $cacheReadTimes = 0;

    public int $cacheWriteTimes = 0;

    /** @var array<string, array<string, int>> */
    public array $keyHits = [];

    /** @var array<int, string> */
    public array $languageFolderArray = [];

    public ?\Redis $redis = null;

    public static function instance(): ?self
    {
        return app(self::class);
    }

    public function __construct()
    {
        // Skip the connection attempt entirely while the shared breaker is
        // open — a dead Redis costs ~5-10s of DNS/connect stalls otherwise.
        if (! RedisGuard::available()) {
            return;
        }

        $client = RedisGuard::attempt(static fn () => Redis::connection('default')->client());
        if (! $client instanceof \Redis) {
            return;
        }

        $this->isEnabled = (bool) RedisGuard::attempt(static fn () => $client->ping(), false);
        if ($this->isEnabled) {
            $this->redis = $client;
        }
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setClearCache(int $isEnabled): void
    {
        $this->clearCache = $isEnabled;
    }

    /** @return array<int, string> */
    public function getLanguageFolderArray(): array
    {
        return $this->languageFolderArray;
    }

    /** @param array<int, string> $languageFolderArray */
    public function setLanguageFolderArray(array $languageFolderArray): void
    {
        $this->languageFolderArray = $languageFolderArray;
    }

    public function getClearCache(): int
    {
        return $this->clearCache;
    }

    // Wrapper for Memcache::set, with the zlib option removed and default duration of 1 hour
    public function cache_value(string $Key, mixed $Value, int $Duration = 3600): void
    {
        if (! $this->getIsEnabled() || $this->redis === null) {
            return;
        }
        $Value = $this->serialize($Value);
        $redis = $this->redis;
        RedisGuard::attempt(fn () => $redis->set($Key, $Value, $Duration));
        $this->cacheWriteTimes++;
        $this->keyHits['write'][$Key] = ! isset($this->keyHits['write'][$Key]) ? 1 : $this->keyHits['write'][$Key] + 1;
    }

    // Wrapper for Memcache::get. Why? Because wrappers are cool.
    public function get_value(string $Key): mixed
    {
        if (! $this->getIsEnabled()) {
            return false;
        }
        if ($this->getClearCache()) {
            $this->delete_value($Key);

            return false;
        }
        // Xia Zuojie: we disable the following lock feature 'cause we don't need it and it doubles the time to fetch a value from a key

        if ($this->redis === null) {
            return false;
        }
        $redis = $this->redis;
        $Return = RedisGuard::attempt(fn () => $redis->get($Key), false);
        $Return = $Return !== null ? $this->unserialize($Return) : null;
        $this->cacheReadTimes++;
        $this->keyHits['read'][$Key] = ! isset($this->keyHits['read'][$Key]) ? 1 : $this->keyHits['read'][$Key] + 1;

        return $Return;
    }

    /**
     * Batch wrapper for mget — one Redis round-trip instead of one per key.
     *
     * @param  array<int, string>  $Keys
     * @return array<string, mixed> key => unserialized value (false on miss)
     */
    public function get_values(array $Keys): array
    {
        $result = array_fill_keys($Keys, false);
        if ($Keys === [] || ! $this->getIsEnabled()) {
            return $result;
        }
        if ($this->getClearCache()) {
            foreach ($Keys as $Key) {
                $this->delete_value($Key);
            }

            return $result;
        }
        if ($this->redis === null) {
            return $result;
        }
        $redis = $this->redis;
        $keys = array_values($Keys);
        $rows = RedisGuard::attempt(fn () => $redis->mget($keys), false);
        if (! is_array($rows)) {
            $rows = array_fill(0, count($keys), false);
        }
        foreach ($keys as $i => $Key) {
            $result[$Key] = $this->unserialize($rows[$i] ?? false);
            $this->keyHits['read'][$Key] = ! isset($this->keyHits['read'][$Key]) ? 1 : $this->keyHits['read'][$Key] + 1;
        }
        $this->cacheReadTimes += count($keys);

        return $result;
    }

    // Wrapper for Memcache::delete. For a reason, see above.
    public function delete_value(string $Key, bool $AllLang = false): int
    {
        if (! $this->getIsEnabled() || $this->redis === null) {
            return 0;
        }
        $redis = $this->redis;
        $deleted = (int) RedisGuard::attempt(fn () => $redis->del($Key), 0);
        if ($AllLang) {
            $langfolder_array = $this->getLanguageFolderArray();
            foreach ($langfolder_array as $lf) {
                RedisGuard::attempt(fn () => $redis->del($lf.'_'.$Key));
            }
        }

        return $deleted;
    }

    public function getCacheReadTimes(): int
    {
        return $this->cacheReadTimes;
    }

    public function getCacheWriteTimes(): int
    {
        return $this->cacheWriteTimes;
    }

    /** @return array<string, int> */
    public function getKeyHits(string $type = 'read'): array
    {
        return $this->keyHits[$type] ?? [];
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) && ! in_array($value, [INF, -INF]) && ! is_nan((float) $value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * Redis is an internal trusted store (not user input), so unserialize
     * is safe here. The RCE risk from unserialize() requires an attacker
     * to already have write access to Redis, which means the system is
     * already compromised.
     *
     * `allowed_classes: false` is set as defence-in-depth: all cached
     * values are arrays, integers, strings, or HTML — never PHP objects —
     * so blocking object deserialization eliminates the attack surface
     * entirely without breaking any legitimate use case.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        if ($value === false || $value === null) {
            return false;
        }

        if (is_numeric($value)) {
            // Preserve the original PHP type: integers stay int, floats stay
            // float.  Redis stores everything as strings, so a cached int(0)
            // comes back as "0" (string) — which breaks int|float type
            // declarations in callers (e.g. Strings::isOrAre).
            return (float) $value == (int) $value && strpos((string) $value, '.') === false
                ? (int) $value
                : (float) $value;
        }

        return unserialize($value, ['allowed_classes' => false]);
    }
}
