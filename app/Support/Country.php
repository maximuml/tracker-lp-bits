<?php

namespace App\Support;

use Nexus\Database\NexusDB;

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
     * @param  mixed  $cache
     * @return array<string, mixed>|null
     */
    public static function row(mixed $cache, int|string $id): ?array
    {
        $cacheKey = 'country_' . $id . '_content';
        $row = method_exists($cache, 'get_value') ? $cache->get_value($cacheKey) : false;

        if ($row === false) {
            $result = NexusDB::table('countries')->where('id', $id)->first();
            $row = $result ? (array) $result : null;
            if (method_exists($cache, 'cache_value')) {
                $cache->cache_value($cacheKey, $row, 86400);
            }
        }

        return $row ?: null;
    }
}
