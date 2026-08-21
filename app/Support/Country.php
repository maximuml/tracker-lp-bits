<?php

namespace App\Support;

use App\Repositories\CountryRepository;

/**
 * Legacy country helper extracted from `include/functions.php`.
 *
 * Backs `get_country_row()`.
 */
final class Country
{
    /**
     * Fetch a country row, using the legacy cache layer.
     *
     * Mirrors `get_country_row()`.
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function row(mixed $cache, int|string $id): ?array
    {
        $cacheKey = 'country_'.$id.'_content';
        $row = is_object($cache) && method_exists($cache, 'get_value') ? $cache->get_value($cacheKey) : false;

        if ($row === false) {
            $row = CountryRepository::findById($id);
            if (is_object($cache) && method_exists($cache, 'cache_value')) {
                $cache->cache_value($cacheKey, $row, 86400);
            }
        }

        return $row ?: null;
    }

    /**
     * Context-aware wrapper for {@see row()}.
     *
     * @return array<string, mixed>|null
     */
    public static function rowWithContext(int|string $id): ?array
    {
        return self::row(SupportContext::getCache(), $id);
    }
}
