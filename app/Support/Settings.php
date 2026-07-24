<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Legacy settings bulk-save helper extracted from `include/functions.php`.
 *
 * Backs `saveSetting()`. Performs an `INSERT ... ON DUPLICATE KEY UPDATE`
 * against the `settings` table and then clears the settings cache.
 */
final class Settings
{
    /**
     * Persist a batch of settings under the given `$prefix`.
     *
     * Mirrors `saveSetting($prefix, $nameAndValue, $autoload)`.
     */
    public static function saveBatch(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
    {
        $prefix = strtolower($prefix);
        $datetimeNow = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO settings (name, value, created_at, updated_at, autoload) VALUES ';
        $data = [];

        foreach ($nameAndValue as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $data[] = sprintf(
                "(%s, %s, %s, %s, '%s')",
                sqlesc("$prefix.$name"),
                sqlesc($value),
                sqlesc($datetimeNow),
                sqlesc($datetimeNow),
                $autoload
            );
        }

        $sql .= implode(',', $data) . ' ' . NexusDB::upsertField(['name'], ['value']);
        NexusDB::statement($sql);
        \clear_setting_cache();
        \do_action('nexus_setting_update');
    }
}
