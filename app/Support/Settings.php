<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\SettingRepository;

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
            self::$settings = SettingRepository::getAll();
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
            self::$fromDb = SettingRepository::getAll();
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
        SettingRepository::saveBatch($prefix, $nameAndValue, $autoload);
    }
}
