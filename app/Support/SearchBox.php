<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use Illuminate\Support\Arr;

/**
 * Legacy searchbox helper extracted from `include/functions.php`.
 *
 * Backs `get_searchbox_value()`.
 */
final class SearchBox
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $rows = null;

    /**
     * Return a value from the searchbox configuration row.
     *
     * Mirrors `get_searchbox_value($mode, $item)`.
     */
    public static function value(?NexusCache $cache, int|string $mode, string $item): mixed
    {
        if (self::$rows === null) {
            $cached = $cache !== null ? $cache->get('search_box_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$rows = $cached;
            } else {
                self::$rows = self::searchBoxRepository()->getAllRows();
                if ($cache !== null) {
                    $cache->put('search_box_content', self::$rows, 100500);
                }
            }
        }

        return self::$rows[$mode][$item] ?? '';
    }

    /**
     * Return the rows for a search-box taxonomy table.
     *
     * Mirrors `searchbox_item_list()`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function itemList(?NexusCache $cache, string $table, int|string $mode): array
    {
        $mode = (int) $mode;
        $cacheKey = "{$table}_list_mode_{$mode}";

        if ($cache !== null) {
            $ret = $cache->get($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        if ($mode > 0) {
            $ret = self::searchBoxRepository()->getTaxonomyList($table, $mode);
        } else {
            $ret = self::searchBoxRepository()->getTaxonomyList($table, 0);
        }

        if ($cache !== null) {
            $cache->put($cacheKey, $ret, 3600);
        }

        return $ret;
    }

    /**
     * Return the search-box IDs that must be loaded for the current script.
     *
     * Mirrors `list_require_search_box_id()`.
     *
     * @return list<int>
     */
    public static function requiredIds(): array
    {
        $setting = SiteConfig::current()->main->toArray();
        $maps = [
            'torrents' => [$setting['browsecat']],
            'usercp' => [$setting['browsecat']],
            'getrss' => [$setting['browsecat']],
            'userdetails' => [$setting['browsecat']],
            'offers' => [$setting['browsecat']],
            'details' => [$setting['browsecat']],
            'search' => [$setting['browsecat']],
        ];
        $script = RequestContext::instance()->getScript();

        return array_values(array_map('intval', Arr::wrap($maps[$script] ?? [])));
    }

    /**
     * Read a search-box setting, fetching the cache from the request context.
     *
     * Backs the legacy `get_searchbox_value()` helper.
     */
    public static function valueWithContext(int|string $mode, string $item = 'showsubcat'): mixed
    {
        return self::value(NexusCache::instance(), $mode, $item);
    }

    /**
     * Read a search-box taxonomy list, fetching the cache from the request context.
     *
     * Backs the legacy `searchbox_item_list()` helper.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function itemListWithContext(string $table, int|string $mode): array
    {
        return self::itemList(NexusCache::instance(), $table, $mode);
    }

    private static function searchBoxRepository(): SearchBoxRepositoryInterface
    {
        return app(SearchBoxRepositoryInterface::class);
    }
}
