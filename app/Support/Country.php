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
    public static function row($cache, int|string $id): ?array
    {
        $cacheKey = 'country_' . $id . '_content';
        $row = method_exists($cache, 'get_value') ? $cache->get_value($cacheKey) : false;

        if ($row === false) {
            $result = NexusDB::getInstance()->query('SELECT * FROM countries WHERE id=' . \App\Support\LegacyDb::escape($id) . ' LIMIT 1');
            $row = NexusDB::getInstance()->fetchAssoc($result);
            if (method_exists($cache, 'cache_value')) {
                $cache->cache_value($cacheKey, $row, 86400);
            }
        }

        return $row ?: null;
    }
}
