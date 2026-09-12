<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\RedisGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Shared passkey → tracker-user lookup for announce/scrape.
 *
 * Negative results are never cached: caching an empty row would poison a
 * valid passkey for the full TTL whenever the DB lookup ran during a
 * transient window (fresh seed, replication lag, mid-deploy). A cached
 * empty array is treated as a miss and self-heals on the next request.
 */
final class PasskeyUserLookup
{
    private const CACHE_TTL = 3600;

    /**
     * @return array<string, mixed> empty array when the passkey is unknown
     */
    public function find(string $passkey): array
    {
        $cacheKey = "user_passkey_{$passkey}_content";
        $cached = RedisGuard::attempt(static fn () => Cache::get($cacheKey));

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $user = self::query($passkey);
        if ($user !== []) {
            RedisGuard::attempt(static function () use ($cacheKey, $user, $passkey) {
                Cache::put($cacheKey, $user, self::CACHE_TTL);
                Redis::connection()->client()->del("passkey_invalid:{$passkey}");
            });
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private static function query(string $passkey): array
    {
        $user = User::query()
            ->select([
                'id', 'username', 'downloadpos', 'enabled', 'uploaded', 'downloaded',
                'class', 'parked', 'clientselect', 'showclienterror', 'passkey',
                'donor', 'donoruntil', 'seedbonus', 'tracker_url_id',
            ])
            ->where('passkey', $passkey)
            ->first();

        return $user ? $user->toArray() : [];
    }
}
