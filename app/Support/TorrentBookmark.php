<?php

namespace App\Support;

use Nexus\Database\NexusDB;

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
     * @param  mixed  $cache
     * @return array<int, int>
     */
    public static function bookmarkArray(mixed $cache, int|string $userId): array
    {
        $userId = (int) $userId;
        $cacheKey = 'user_' . $userId . '_bookmark_array';

        if (method_exists($cache, 'get_value')) {
            $ret = $cache->get_value($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        $rows = NexusDB::table('bookmarks')->where('userid', $userId)->get()->map(fn ($row) => (array) $row)->all();
        if (empty($rows)) {
            $ret = [0];
        } else {
            $ret = array_map(fn ($row) => (int) $row['torrentid'], $rows);
        }

        if (method_exists($cache, 'cache_value')) {
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
     * @param  mixed  $cache
     * @param  array<string, string>  $labels
     */
    public static function stateMarkup(mixed $cache, int|string $userId, int|string $torrentId, bool $text = false, array $labels = []): string
    {
        $userId = (int) $userId;
        $torrentId = (int) $torrentId;
        $bookmarks = self::bookmarkArray($cache, $userId);

        $bookmarked = in_array($torrentId, $bookmarks, false);

        if (! $bookmarked) {
            return $text
                ? ($labels['title_bookmark_torrent'] ?? '')
                : '<img class="delbookmark" src="pic/trans.gif" alt="Unbookmarked" title="' . ($labels['title_bookmark_torrent'] ?? '') . '" />';
        }

        return $text
            ? ($labels['title_delbookmark_torrent'] ?? '')
            : '<img class="bookmark" src="pic/trans.gif" alt="Bookmarked" title="' . ($labels['title_delbookmark_torrent'] ?? '') . '" />';
    }

    /**
     * Context-aware wrapper for {@see stateMarkup()}.
     * Mirrors the legacy `get_torrent_bookmark_state()` helper.
     */
    public static function stateMarkupWithContext(int|string $userId, int|string $torrentId, bool $text = false): string
    {
        $cache = \App\Support\SupportContext::getCache();
        $lang = \App\Support\SupportContext::getLangFunctions();

        return self::stateMarkup($cache, $userId, $torrentId, $text, [
            'title_bookmark_torrent' => $lang['title_bookmark_torrent'] ?? '',
            'title_delbookmark_torrent' => $lang['title_delbookmark_torrent'] ?? '',
        ]);
    }
}
