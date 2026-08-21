<?php

namespace App\Support;

use App\Repositories\CategoryRepository;
use App\Support\Cache\LegacyRedisCache;

/**
 * Legacy category / icon helpers extracted from `include/functions.php`.
 *
 * Backs `get_category_row`, `get_category_icon_row`, `get_second_icon`
 * and `return_category_image`.
 */
final class Category
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $iconRows = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $categoryRows = null;

    /**
     * Return the category-icon row for `$typeId`.
     *
     * Mirrors `get_category_icon_row()`.
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function iconRow(?LegacyRedisCache $cache, int|string $typeId): ?array
    {
        $typeId = (int) $typeId ?: 1;

        if (self::$iconRows === null) {
            $cached = $cache !== null ? $cache->get_value('category_icon_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$iconRows = $cached;
            } else {
                self::$iconRows = CategoryRepository::getIconRows();
                if ($cache !== null) {
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
    public static function row(?LegacyRedisCache $cache, int|string|null $catId = null): ?array
    {
        if (self::$categoryRows === null) {
            $cached = $cache !== null ? $cache->get_value('category_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$categoryRows = $cached;
            } else {
                self::$categoryRows = CategoryRepository::getCategoryRows();
                if ($cache !== null) {
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
     * Context-aware wrapper for {@see row()}.
     *
     * @return array<string, mixed>|null
     */
    public static function rowWithContext(int|string|null $catId = null): ?array
    {
        return self::row(SupportContext::getCache(), $catId);
    }

    /**
     * Context-aware wrapper for {@see iconRow()}.
     *
     * @return array<string, mixed>|null
     */
    public static function iconRowWithContext(int|string $typeId): ?array
    {
        return self::iconRow(SupportContext::getCache(), $typeId);
    }

    /**
     * Context-aware wrapper for {@see listByMode()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByModeWithContext(int|string $catmode = 1): array
    {
        return self::listByMode(SupportContext::getCache(), $catmode);
    }

    /**
     * Build the additional second-icon `<img>` tag for a torrent row.
     *
     * Mirrors `get_second_icon()`.
     */
    /**
     * @param  array<string, mixed>  $row
     */
    public static function secondIcon(?LegacyRedisCache $cache, array $row, string $catFolder): string
    {
        $source = $row['source'] ?? '';
        $medium = $row['medium'] ?? '';
        $codec = $row['codec'] ?? '';
        $standard = $row['standard'] ?? '';
        $processing = $row['processing'] ?? '';
        $audiocodec = $row['audiocodec'] ?? '';
        $mode = $row['search_box_id'] ?? 0;

        $cacheKey = 'secondicon_'.$source.'_'.$medium.'_'.$codec.'_'.$standard.'_'.$processing.'_'.$audiocodec.'_content';
        $sirow = $cache !== null ? $cache->get_value($cacheKey) : false;

        if ($sirow === false) {
            $sirowData = CategoryRepository::findSecondIcon($row);
            $sirow = $sirowData ?? 'not allowed';
            if ($cache !== null) {
                $cache->cache_value($cacheKey, $sirow, 600);
            }
        }

        if ($sirow === 'not allowed') {
            return '<img src="pic/cattrans.gif" style="background-image: url(pic/'.$catFolder.'/additional/notallowed.png);" title="Not Allowed" alt="Not Allowed" />';
        }

        return '<img'.($sirow['class_name'] ? ' class="'.$sirow['class_name'].'"' : '').' src="pic/cattrans.gif" style="background-image: url(pic/'.$catFolder.'/additional/'.$sirow['image'].');" alt="'.$sirow['name'].'" title="'.$sirow['name'].'" />';
    }

    /**
     * Context-aware wrapper for {@see secondIcon()}.
     * Mirrors the legacy `get_second_icon()` helper.
     *
     * @param  array<string, mixed>  $row
     */
    public static function secondIconWithContext(array $row): string
    {
        $cache = SupportContext::getCache();
        $catFolder = Path::categoryFolderForIdWithContext($row['category'] ?? '');

        return self::secondIcon($cache, $row, $catFolder);
    }

    /**
     * Return the category list for a search mode.
     *
     * Mirrors `genrelist()`.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listByMode(?LegacyRedisCache $cache, int|string $catmode = 1): array
    {
        $catmode = (int) $catmode;
        $cacheKey = 'category_list_mode_'.$catmode;

        if ($cache !== null) {
            $ret = $cache->get_value($cacheKey);
            if ($ret !== false && is_array($ret)) {
                return $ret;
            }
        }

        $ret = CategoryRepository::getCategoriesByMode($catmode);

        if ($cache !== null) {
            $cache->cache_value($cacheKey, $ret, 3600);
        }

        return $ret;
    }

    /**
     * Build the category image tag for a category id.
     *
     * Mirrors `return_category_image()`.
     */
    public static function imageTagWithContext(int|string $categoryId, string $link = ''): string
    {
        return self::imageTag($categoryId, $link);
    }

    public static function imageTag(int|string $categoryId, string $link = ''): string
    {
        static $cache = [];

        if (! isset($cache[$categoryId])) {
            $categoryRow = self::rowWithContext($categoryId);
            $catImgUrl = Path::categoryFolderForIdWithContext($categoryId);
            $className = (string) ($categoryRow['class_name'] ?? '');
            $name = (string) ($categoryRow['name'] ?? '');
            $image = (string) ($categoryRow['image'] ?? '');
            $cache[$categoryId] = '<img'.($className ? ' class="'.$className.'"' : '').' src="pic/cattrans.gif" alt="'.$name.'" title="'.$name.'" style="background-image: url(pic/'.$catImgUrl.'/'.$image.');" />';
        }

        $catImg = $cache[$categoryId];

        if ($link !== '') {
            $catImg = '<a href="'.$link.'cat='.$categoryId.'">'.$catImg.'</a>';
        }

        return $catImg;
    }
}
