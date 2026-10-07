<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\TorrentStatsService;
use App\Support\Cache\LegacyRedisCache;

/**
 * Legacy torrent-bookmark helpers extracted from `include/functions.php`.
 *
 * Backs `return_torrent_bookmark_array()` and `get_torrent_bookmark_state()`.
 */
final class TorrentBookmark
{
    /**
     * Return the cached list of bookmarked torrent ids for a user.
     *
     * Mirrors `return_torrent_bookmark_array()`.
     *
     * @return array<int, int>
     */
    public static function bookmarkArray(mixed $cache, int|string $userId): array
    {
        $userId = (int) $userId;
        $cacheKey = 'user_'.$userId.'_bookmark_array';

        if (is_object($cache) && method_exists($cache, 'get_value')) {
            $ret = $cache->get_value($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        $ret = app(TorrentStatsService::class)->getBookmarkTorrentIds($userId);

        if (is_object($cache) && method_exists($cache, 'cache_value')) {
            $cache->cache_value($cacheKey, $ret, 132800);
        }

        return $ret;
    }

    /**
     * Return the bookmark/unbookmark action text or icon for a torrent.
     *
     * Mirrors `get_torrent_bookmark_state()`.
     */
    /**
     * @param  array<string, string>  $labels
     */
    public static function stateMarkup(mixed $cache, int|string $userId, int|string $torrentId, bool $text = false, array $labels = []): string
    {
        $bookmarked = self::isBookmarked($cache, $userId, $torrentId);

        if (! $bookmarked) {
            return $text
                ? ($labels['title_bookmark_torrent'] ?? '')
                : trim(view('support._bookmark-img', ['cls' => 'delbookmark', 'alt' => 'Unbookmarked', 'title' => (string) ($labels['title_bookmark_torrent'] ?? '')])->render());
        }

        return $text
            ? ($labels['title_delbookmark_torrent'] ?? '')
            : trim(view('support._bookmark-img', ['cls' => 'bookmark', 'alt' => 'Bookmarked', 'title' => (string) ($labels['title_delbookmark_torrent'] ?? '')])->render());
    }

    /**
     * Whether the torrent is in the user's bookmark list — typed
     * counterpart of {@see stateMarkup()} for view-model assembly.
     */
    public static function isBookmarked(mixed $cache, int|string $userId, int|string $torrentId): bool
    {
        return in_array((int) $torrentId, self::bookmarkArray($cache, (int) $userId), false);
    }

    /**
     * Context-aware wrapper for {@see stateMarkup()}.
     * Mirrors the legacy `get_torrent_bookmark_state()` helper.
     */
    public static function stateMarkupWithContext(int|string $userId, int|string $torrentId, bool $text = false): string
    {
        $cache = LegacyRedisCache::instance();

        return self::stateMarkup($cache, $userId, $torrentId, $text, [
            'title_bookmark_torrent' => __('functions.title_bookmark_torrent'),
            'title_delbookmark_torrent' => __('functions.title_delbookmark_torrent'),
        ]);
    }
}
