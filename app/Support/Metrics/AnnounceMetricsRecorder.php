<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use App\Support\RedisGuard;
use Illuminate\Support\Facades\Redis;

/**
 * T-23: Records announce rejection reasons to Redis for /metrics.
 *
 * Increments a Redis counter per rejection reason, enabling the
 * /metrics endpoint to expose `nexus_announce_rejections_total{reason="..."}`
 * in Prometheus format.
 *
 * Failures (e.g. Redis unavailable) are silently ignored.
 */
final class AnnounceMetricsRecorder
{
    /**
     * Known rejection reason categories (cardinality-controlled).
     */
    private const REASON_CATEGORIES = [
        'invalid_passkey',
        'disabled_account',
        'parked_account',
        'download_disabled',
        'torrent_not_registered',
        'torrent_banned',
        'torrent_not_approved',
        'client_denied',
        'browser_blocked',
        'port_blocked',
        'paid_torrent_failure',
        'validation_error',
        'other',
    ];

    /**
     * Record an announce rejection by reason category.
     */
    public static function recordRejection(string $rawReason): void
    {
        $category = self::categorize($rawReason);

        // Best-effort — when Redis is down the breaker fails fast instead of
        // paying ~5-10s of connect stalls on every rejected announce.
        RedisGuard::attempt(static fn () => Redis::connection()->incr("metrics:announce_rejections:{$category}"));
    }

    /**
     * Map a raw rejection message to a fixed cardinality category.
     */
    public static function categorize(string $reason): string
    {
        $reason = strtolower($reason);

        return match (true) {
            str_contains($reason, 'invalid passkey') => 'invalid_passkey',
            str_contains($reason, 'account is disabled') => 'disabled_account',
            str_contains($reason, 'account is parked') => 'parked_account',
            str_contains($reason, 'downloading privileges') => 'download_disabled',
            str_contains($reason, 'torrent not registered') => 'torrent_not_registered',
            str_contains($reason, 'torrent banned') => 'torrent_banned',
            str_contains($reason, 'torrent review not approved') => 'torrent_not_approved',
            str_contains($reason, 'browser access blocked') => 'browser_blocked',
            str_contains($reason, 'port') => 'port_blocked',
            str_contains($reason, 'paid torrent') => 'paid_torrent_failure',
            str_contains($reason, 'validation') => 'validation_error',
            default => 'other',
        };
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return self::REASON_CATEGORIES;
    }
}
