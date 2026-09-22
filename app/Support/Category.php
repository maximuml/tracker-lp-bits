<?php

declare(strict_types=1);

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

    /** @var array<int|string, array{iconClass: string, name: string}> */
    private static array $iconDataCache = [];

    /**
     * Clear per-request memoized state so long-running workers (Octane,
     * queue) re-read categories/icons instead of serving stale names
     * cached before an admin edit or delete.
     */
    public static function resetState(): void
    {
        self::$iconRows = null;
        self::$categoryRows = null;
        self::$iconDataCache = [];
    }

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
                self::$iconRows = app(CategoryRepository::class)->getIconRows();
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
                self::$categoryRows = app(CategoryRepository::class)->getCategoryRows();
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
        return self::row(app(LegacyRedisCache::class), $catId);
    }

    /**
     * Context-aware wrapper for {@see iconRow()}.
     *
     * @return array<string, mixed>|null
     */
    public static function iconRowWithContext(int|string $typeId): ?array
    {
        return self::iconRow(app(LegacyRedisCache::class), $typeId);
    }

    /**
     * Context-aware wrapper for {@see listByMode()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listByModeWithContext(int|string $catmode = 1): array
    {
        return self::listByMode(app(LegacyRedisCache::class), $catmode);
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

        $ret = app(CategoryRepository::class)->getCategoriesByMode($catmode);

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
        $catImg = self::iconImg($categoryId);

        if ($link !== '') {
            $catImg = '<a href="'.$link.'cat='.$categoryId.'">'.$catImg.'</a>';
        }

        return $catImg;
    }

    /**
     * Typed category-icon data for view-model assembly — the data
     * counterpart of {@see imageTag()}.
     *
     * @return array{iconClass: string, name: string}
     */
    public static function iconData(int|string $categoryId): array
    {
        if (! isset(self::$iconDataCache[$categoryId])) {
            $categoryRow = self::rowWithContext($categoryId);
            $name = (string) ($categoryRow['name'] ?? '');
            self::$iconDataCache[$categoryId] = [
                'iconClass' => (string) ($categoryRow['class_name'] ?? ''),
                // Missing/stale cache row must not leave links nameless —
                // an empty alt makes the ?cat= anchor a link-name violation.
                'name' => $name !== '' ? $name : '#'.$categoryId,
            ];
        }

        return self::$iconDataCache[$categoryId];
    }

    /**
     * Typed second-icon data for view-model assembly — the data
     * counterpart of {@see secondIcon()}. The "not allowed" sentinel
     * maps to `{iconClass: '', name: 'Not Allowed'}`.
     *
     * @param  array<string, mixed>  $row
     * @return array{iconClass: string, name: string}
     */
    public static function secondIconData(array $row): array
    {
        $cache = app(LegacyRedisCache::class);
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
            $sirowData = app(CategoryRepository::class)->findSecondIcon($row);
            $sirow = $sirowData ?? 'not allowed';
            if ($cache !== null) {
                $cache->cache_value($cacheKey, $sirow, 600);
            }
        }

        if ($sirow === 'not allowed') {
            return ['iconClass' => '', 'name' => 'Not Allowed'];
        }

        return ['iconClass' => (string) ($sirow['class_name'] ?? ''), 'name' => (string) ($sirow['name'] ?? '')];
    }

    private static function iconImg(int|string $categoryId): string
    {
        $data = self::iconData($categoryId);

        return '<img'.($data['iconClass'] !== '' ? ' class="'.$data['iconClass'].'"' : '').' src="pic/cattrans.gif" alt="'.$data['name'].'" title="'.$data['name'].'" />';
    }
}
