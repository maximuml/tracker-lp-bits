<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy searchbox helper extracted from `include/functions.php`.
 *
 * Backs `get_searchbox_value()`.
 */
final class SearchBox
{
    private static ?array $rows = null;

    /**
     * Return a value from the searchbox configuration row.
     *
     * Mirrors `get_searchbox_value($mode, $item)`.
     */
    public static function value($cache, int|string $mode, string $item): mixed
    {
        if (self::$rows === null) {
            $cached = method_exists($cache, 'get_value') ? $cache->get_value('search_box_content') : false;
            if ($cached !== false && is_array($cached)) {
                self::$rows = $cached;
            } else {
                self::$rows = [];
                $result = NexusDB::getInstance()->query('SELECT * FROM searchbox ORDER BY id ASC');
                while ($row = NexusDB::getInstance()->fetchAssoc($result)) {
                    if (isset($row['extra'])) {
                        $row['extra'] = json_decode($row['extra'], true);
                    }
                    if (isset($row['section_name'])) {
                        $row['section_name'] = json_decode($row['section_name'], true);
                    }
                    self::$rows[$row['id']] = $row;
                }
                if (method_exists($cache, 'cache_value')) {
                    $cache->cache_value('search_box_content', self::$rows, 100500);
                }
            }
        }

        return self::$rows[$mode][$item] ?? '';
    }
}
