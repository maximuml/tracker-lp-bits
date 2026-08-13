<?php

namespace App\Support;

use App\Models\Setting;
use Nexus\Database\NexusDB;

/**
 * Legacy settings helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `saveSetting()`, `Settings::get()` and `Settings::fromDb()`.
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
     * Mirrors the legacy `get_setting()` helper.
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
     * Mirrors the legacy `get_setting_from_db()` helper.
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
     *
     * @param  array<string, mixed>  $nameAndValue
     */
    public static function saveBatch(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
    {
        $prefix = strtolower($prefix);
        $datetimeNow = date('Y-m-d H:i:s');
        $records = [];

        foreach ($nameAndValue as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $records[] = [
                'name' => "$prefix.$name",
                'value' => $value,
                'created_at' => $datetimeNow,
                'updated_at' => $datetimeNow,
                'autoload' => $autoload,
            ];
        }

        if (! empty($records)) {
            Setting::query()->upsert($records, ['name'], ['value', 'updated_at']);
        }
        Cache::clearSettings();
        Hooks::doAction('nexus_setting_update');
    }
}
