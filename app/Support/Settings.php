<?php

namespace App\Support;

use App\Models\Setting;
use Nexus\Database\NexusDB;

/**
 * Legacy settings helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `saveSetting()`, `get_setting()` and `get_setting_from_db()`.
 */
final class Settings
{
    /** @var array<string, mixed>|null */
    private static ?array $settings = null;

    /** @var array<string, mixed>|null */
    private static ?array $fromDb = null;

    /**
     * Read a setting from the in-request cache, falling back to the DB.
     *
     * Mirrors `get_setting()`.
     */
    public static function get(?string $name = null, mixed $default = null): mixed
    {
        if (self::$settings === null) {
            self::$settings = NexusDB::remember('nexus_settings_in_nexus', 600, fn () => Setting::getFromDb());
        }

        if ($name === null) {
            return self::$settings;
        }

        return Arrays::get(self::$settings, $name, $default);
    }

    /**
     * Read a setting directly from the database (no cache).
     *
     * Mirrors `get_setting_from_db()`.
     */
    public static function fromDb(?string $name = null, mixed $default = null): mixed
    {
        if (self::$fromDb === null) {
            self::$fromDb = Setting::getFromDb();
        }

        if ($name === null) {
            return self::$fromDb;
        }

        return Arrays::get(self::$fromDb, $name, $default);
    }

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
                \App\Support\LegacyDb::escape("$prefix.$name"),
                \App\Support\LegacyDb::escape($value),
                \App\Support\LegacyDb::escape($datetimeNow),
                \App\Support\LegacyDb::escape($datetimeNow),
                $autoload
            );
        }

        $sql .= implode(',', $data) . ' ' . NexusDB::upsertField(['name'], ['value']);
        NexusDB::statement($sql);
        \clear_setting_cache();
        \do_action('nexus_setting_update');
    }
}
