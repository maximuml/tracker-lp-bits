<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Support\Locale;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\Redis;

/**
 * Tagged cache service built on top of Laravel's Cache facade.
 *
 * Provides a single, unified caching layer that replaces the dual-layer
 * system (LegacyRedisCache + Cache facade). All operations go through
 * Laravel's Cache manager, which supports tags for grouped invalidation
 * and uses the configured Redis backend.
 *
 * Usage with Keys enum:
 *   $svc = app(TaggedCacheService::class);
 *   $svc->remember(Keys::USER_CONTENT, ['id' => 42], fn() => User::find(42));
 *   $svc->forget(Keys::USER_CONTENT, ['id' => 42]);
 *   $svc->flushTag('user');  // flush all user-tagged keys
 *
 * Usage with raw keys (for gradual migration):
 *   $svc->put('my_key', $value, 3600);
 *   $svc->get('my_key');
 */
final class TaggedCacheService
{
    /**
     * The underlying cache repository (Redis by default).
     */
    private function store(): CacheRepository
    {
        return CacheFacade::store();
    }

    /**
     * Store a value in the cache with the key's default TTL and tags.
     *
     * @param  array<string, int|string>  $params  Placeholder substitutions
     */
    public function put(Keys $key, array $params, mixed $value): void
    {
        $this->store()->tags($key->tags())->put($key->key($params), $value, $key->ttl());
    }

    /**
     * Store a value with a custom TTL (overrides the key's default).
     *
     * @param  array<string, int|string>  $params
     */
    public function putWithTtl(Keys $key, array $params, mixed $value, int $ttl): void
    {
        $this->store()->tags($key->tags())->put($key->key($params), $value, $ttl);
    }

    /**
     * Remember: get from cache or execute callback and store.
     *
     * @param  array<string, int|string>  $params
     * @param  \Closure(): mixed  $callback
     */
    public function remember(Keys $key, array $params, \Closure $callback): mixed
    {
        return $this->store()->tags($key->tags())->remember($key->key($params), $key->ttl(), $callback);
    }

    /**
     * Remember with a custom TTL.
     *
     * @param  array<string, int|string>  $params
     * @param  \Closure(): mixed  $callback
     */
    public function rememberWithTtl(Keys $key, array $params, int $ttl, \Closure $callback): mixed
    {
        return $this->store()->tags($key->tags())->remember($key->key($params), $ttl, $callback);
    }

    /**
     * Get a value from the cache, returning the default if missing.
     *
     * @param  array<string, int|string>  $params
     */
    public function get(Keys $key, array $params, mixed $default = null): mixed
    {
        return $this->store()->tags($key->tags())->get($key->key($params), $default);
    }

    /**
     * Forget a single cache key.
     *
     * @param  array<string, int|string>  $params
     */
    public function forget(Keys $key, array $params): void
    {
        $this->store()->tags($key->tags())->forget($key->key($params));
    }

    /**
     * Flush all keys with the given tag.
     */
    public function flushTag(string $tag): void
    {
        $this->store()->tags([$tag])->flush();
    }

    /**
     * Flush all keys with any of the given tags.
     *
     * @param  list<string>  $tags
     */
    public function flushTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->flushTag($tag);
        }
    }

    // ─── Raw key operations (for gradual migration) ────────────────

    /**
     * Store a raw key with explicit TTL and tags.
     *
     * @param  list<string>  $tags
     */
    public function putRaw(string $key, mixed $value, int $ttl, array $tags = []): void
    {
        if ($tags !== []) {
            $this->store()->tags($tags)->put($key, $value, $ttl);
        } else {
            $this->store()->put($key, $value, $ttl);
        }
    }

    /**
     * Remember a raw key with explicit TTL and tags.
     *
     * @param  list<string>  $tags
     * @param  \Closure(): mixed  $callback
     */
    public function rememberRaw(string $key, int $ttl, \Closure $callback, array $tags = []): mixed
    {
        if ($tags !== []) {
            return $this->store()->tags($tags)->remember($key, $ttl, $callback);
        }

        return $this->store()->remember($key, $ttl, $callback);
    }

    /**
     * Get a raw key.
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    /**
     * Forget a raw key.
     */
    public function forgetRaw(string $key): void
    {
        $this->store()->forget($key);
    }

    /**
     * Forget a raw key and all its locale-prefixed variants.
     *
     * Replicates the behaviour of Cache::forgetWithLocales() for
     * backward compatibility during migration.
     */
    public function forgetWithLocales(string $key): void
    {
        $this->store()->forget($key);
        foreach ($this->availableLocales() as $locale) {
            $this->store()->forget($locale.'_'.$key);
        }
    }

    /**
     * Delete all cache keys matching a Redis SCAN glob pattern.
     *
     * Replicates Cache::forgetByPattern() for backward compatibility.
     */
    public function forgetByPattern(string $pattern): void
    {
        $redis = Redis::connection()->client();
        /** @var int|string $it */
        $it = 0;
        do {
            $keys = $redis->scan($it, $pattern);
            if ($keys !== false) {
                foreach ($keys as $key) {
                    $this->forgetWithLocales($key);
                }
            }
        } while ($it > 0);
    }

    /**
     * @return list<string>
     */
    private function availableLocales(): array
    {
        return array_values(Locale::available());
    }
}
