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
                $result = NexusDB::getInstance()->query('SELECT * FROM caticons ORDER BY id ASC');
                while ($row = NexusDB::getInstance()->fetchAssoc($result)) {
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
                $result = NexusDB::getInstance()->query('SELECT categories.*, searchbox.name AS catmodename FROM categories LEFT JOIN searchbox ON categories.mode=searchbox.id');
                while ($row = NexusDB::getInstance()->fetchAssoc($result)) {
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
            $result = NexusDB::getInstance()->query(
                'SELECT * FROM secondicons WHERE (mode = ' . sqlesc($mode) . ' OR mode = 0) '
                . 'AND (source = ' . sqlesc($source) . ' OR source=0) '
                . 'AND (medium = ' . sqlesc($medium) . ' OR medium=0) '
                . 'AND (codec = ' . sqlesc($codec) . ' OR codec = 0) '
                . 'AND (standard = ' . sqlesc($standard) . ' OR standard = 0) '
                . 'AND (processing = ' . sqlesc($processing) . ' OR processing = 0) '
                . 'AND (audiocodec = ' . sqlesc($audiocodec) . ' OR audiocodec = 0) LIMIT 1'
            );
            $sirow = NexusDB::getInstance()->fetchAssoc($result);
            if (! $sirow) {
                $sirow = 'not allowed';
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

        $ret = NexusDB::select(
            'SELECT id, mode, name, image FROM categories WHERE mode = ' . sqlesc($catmode) . ' ORDER BY sort_index DESC'
        );

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
