<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy category / icon helpers extracted from `include/functions.php`.
 *
 * Backs `get_category_row`, `get_category_icon_row`, `get_second_icon`
 * and `return_category_image`.
 */
final class Category
{
    private static ?array $iconRows = null;
    private static ?array $categoryRows = null;

    /**
     * Return the category-icon row for `$typeId`.
     *
     * Mirrors `get_category_icon_row()`.
     */
    public static function iconRow($cache, int|string $typeId): ?array
    {
        $typeId = (int) $typeId ?: 1;

        if (self::$iconRows === null) {
            $cached = method_exists($cache, 'get_value') ? $cache->get_value('category_icon_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$iconRows = $cached;
            } else {
                self::$iconRows = [];
                foreach (NexusDB::table('caticons')->orderBy('id')->get() as $row) {
                    $row = (array) $row;
                    self::$iconRows[$row['id']] = $row;
                }
                if (method_exists($cache, 'cache_value')) {
                    $cache->cache_value('category_icon_content', self::$iconRows, 156400);
                }
            }
        }

        return self::$iconRows[$typeId] ?? null;
    }

    /**
     * Return one or all category rows.
     *
     * Mirrors `get_category_row($catid)`.
     *
     * @return array<string, mixed>|null
     */
    public static function row($cache, int|string|null $catId = null): ?array
    {
        if (self::$categoryRows === null) {
            $cached = method_exists($cache, 'get_value') ? $cache->get_value('category_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$categoryRows = $cached;
            } else {
                self::$categoryRows = [];
                foreach (NexusDB::table('categories')->leftJoin('searchbox', 'categories.mode', '=', 'searchbox.id')->select('categories.*', 'searchbox.name as catmodename')->get() as $row) {
                    $row = (array) $row;
                    self::$categoryRows[$row['id']] = $row;
                }
                if (method_exists($cache, 'cache_value')) {
                    $cache->cache_value('category_content', self::$categoryRows, 126400);
                }
            }
        }

        if ($catId === null || $catId === '') {
            return self::$categoryRows;
        }

        return self::$categoryRows[$catId] ?? null;
    }

    /**
     * Build the additional second-icon `<img>` tag for a torrent row.
     *
     * Mirrors `get_second_icon()`.
     */
    public static function secondIcon($cache, array $row, string $catFolder): string
    {
        $source = $row['source'] ?? '';
        $medium = $row['medium'] ?? '';
        $codec = $row['codec'] ?? '';
        $standard = $row['standard'] ?? '';
        $processing = $row['processing'] ?? '';
        $audiocodec = $row['audiocodec'] ?? '';
        $mode = $row['search_box_id'] ?? 0;

        $cacheKey = 'secondicon_' . $source . '_' . $medium . '_' . $codec . '_' . $standard . '_' . $processing . '_' . $audiocodec . '_content';
        $sirow = method_exists($cache, 'get_value') ? $cache->get_value($cacheKey) : false;

        if ($sirow === false) {
            $sirow = NexusDB::table('secondicons')
                ->where(function ($query) use ($mode) {
                    $query->where('mode', $mode)->orWhere('mode', 0);
                })
                ->where(function ($query) use ($source) {
                    $query->where('source', $source)->orWhere('source', 0);
                })
                ->where(function ($query) use ($medium) {
                    $query->where('medium', $medium)->orWhere('medium', 0);
                })
                ->where(function ($query) use ($codec) {
                    $query->where('codec', $codec)->orWhere('codec', 0);
                })
                ->where(function ($query) use ($standard) {
                    $query->where('standard', $standard)->orWhere('standard', 0);
                })
                ->where(function ($query) use ($processing) {
                    $query->where('processing', $processing)->orWhere('processing', 0);
                })
                ->where(function ($query) use ($audiocodec) {
                    $query->where('audiocodec', $audiocodec)->orWhere('audiocodec', 0);
                })
                ->first();
            if (! $sirow) {
                $sirow = 'not allowed';
            } else {
                $sirow = (array) $sirow;
            }
            if (method_exists($cache, 'cache_value')) {
                $cache->cache_value($cacheKey, $sirow, 600);
            }
        }

        if ($sirow === 'not allowed') {
            return '<img src="pic/cattrans.gif" style="background-image: url(pic/' . $catFolder . '/additional/notallowed.png);" title="Not Allowed" alt="Not Allowed" />';
        }

        return '<img' . ($sirow['class_name'] ? ' class="' . $sirow['class_name'] . '"' : '') . ' src="pic/cattrans.gif" style="background-image: url(pic/' . $catFolder . '/additional/' . $sirow['image'] . ');" alt="' . $sirow['name'] . '" title="' . $sirow['name'] . '" />';
    }

    /**
     * Return the category list for a search mode.
     *
     * Mirrors `genrelist()`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByMode($cache, int|string $catmode = 1): array
    {
        $catmode = (int) $catmode;
        $cacheKey = 'category_list_mode_' . $catmode;

        if (method_exists($cache, 'get_value')) {
            $ret = $cache->get_value($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        $ret = NexusDB::table('categories')
            ->where('mode', $catmode)
            ->orderBy('sort_index', 'desc')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        if (method_exists($cache, 'cache_value')) {
            $cache->cache_value($cacheKey, $ret, 3600);
        }

        return $ret;
    }

    /**
     * Build the category image tag for a category id.
     *
     * Mirrors `return_category_image()`.
     */
    public static function imageTag(int|string $categoryId, string $link = ''): string
    {
        static $cache = [];

        if (! isset($cache[$categoryId])) {
            $categoryRow = \get_category_row($categoryId);
            $catImgUrl = \get_cat_folder($categoryId);
            $cache[$categoryId] = '<img' . ($categoryRow['class_name'] ? ' class="' . $categoryRow['class_name'] . '"' : '') . ' src="pic/cattrans.gif" alt="' . $categoryRow['name'] . '" title="' . $categoryRow['name'] . '" style="background-image: url(pic/' . $catImgUrl . '/' . $categoryRow['image'] . ');" />';
        }

        $catImg = $cache[$categoryId];

        if ($link !== '') {
            $catImg = '<a href="' . $link . 'cat=' . $categoryId . '">' . $catImg . '</a>';
        }

        return $catImg;
    }
}
