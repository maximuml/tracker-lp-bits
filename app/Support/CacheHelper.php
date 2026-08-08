<?php

namespace App\Support;

/**
 * Cache/page-cache helpers extracted from `include/functions.php`.
 *
 * Backs the legacy `cache_check()`, `cache_save()`, `set_cachetimestamp()`
 * and `reset_cachetimestamp()` helpers.
 */
final class CacheHelper
{
    public static function cacheCheck(string $file = 'cachefile', bool $endpage = true, int $cachetime = 600): bool
    {
        return Cache::pageCheck($file, $endpage, $cachetime);
    }

    public static function cacheSave(string $file = 'cachefile'): void
    {
        Cache::pageSave($file);
    }

    public static function setCacheTimestamp(int|string $id, string $field = 'cache_stamp'): void
    {
        Cache::touchTorrent($id, $field);
    }

    public static function resetCacheTimestamp(int|string $id, string $field = 'cache_stamp'): void
    {
        Cache::resetTorrent($id, $field);
    }
}
