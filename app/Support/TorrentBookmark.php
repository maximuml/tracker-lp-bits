<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\TorrentStatsService;
use App\Support\Cache\NexusCache;

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
    public static function bookmarkArray(?NexusCache $cache, int|string $userId): array
    {
        $userId = (int) $userId;
        $cacheKey = 'user_'.$userId.'_bookmark_array';

        $ret = $cache?->get($cacheKey);
        if (is_array($ret)) {
            return $ret;
        }

        $ret = app(TorrentStatsService::class)->getBookmarkTorrentIds($userId);
        $cache?->put($cacheKey, $ret, 132800);

        return $ret;
    }

    /**
     * Return the bookmark/unbookmark action text or icon for a torrent.
     *
     * Mirrors `get_torrent_bookmark_state()`.
     */
    /**
     * Whether the torrent is in the user's bookmark list — typed
     * counterpart of {@see stateMarkup()} for view-model assembly.
     */
    public static function isBookmarked(?NexusCache $cache, int|string $userId, int|string $torrentId): bool
    {
        return in_array((int) $torrentId, self::bookmarkArray($cache, (int) $userId), false);
    }
}
