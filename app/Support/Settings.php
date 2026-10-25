<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\SettingRepository;
use App\Support\State\SettingsState;

/**
 * Legacy settings helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `saveSetting()`, `Settings::get()` and `Settings::fromDb()`.
 */
final class Settings
{
    /**
     * Read a setting from the in-request cache, falling back to the DB.
     *
     * Mirrors the legacy `get_setting()` helper.
     */
    public static function get(?string $name = null, mixed $default = null): mixed
    {
        if (SettingsState::instance()->settings === null) {
            SettingsState::instance()->settings = self::settingRepository()->getAll();
        }

        if ($name === null) {
            return SettingsState::instance()->settings;
        }

        return Arrays::get(SettingsState::instance()->settings, $name, $default);
    }

    /**
     * Read a setting directly from the database (no cache).
     *
     * Mirrors the legacy `get_setting_from_db()` helper.
     */
    public static function fromDb(?string $name = null, mixed $default = null): mixed
    {
        if (SettingsState::instance()->fromDb === null) {
            SettingsState::instance()->fromDb = self::settingRepository()->getAll();
        }

        if ($name === null) {
            return SettingsState::instance()->fromDb;
        }

        return Arrays::get(SettingsState::instance()->fromDb, $name, $default);
    }

    /**
     * Persist a batch of settings under the given `$prefix`.
     *
     * Mirrors `saveSetting($prefix, $nameAndValue, $autoload)`.
     *
     * @param  array<string, mixed>  $nameAndValue
     */
    public static function saveBatch(string $prefix, array $nameAndValue, bool $autoload = true): void
    {
        self::settingRepository()->saveBatch($prefix, $nameAndValue, $autoload);
    }

    /**
     * Reset the in-memory static caches.
     *
     * Useful in test environments where settings may change between tests
     * (e.g. via DatabaseTransactions rollback) but the static cache persists
     * across tests in the same process.
     */
    public static function resetCache(): void
    {
        SettingsState::instance()->reset();
    }

    private static function settingRepository(): SettingRepository
    {
        return app(SettingRepository::class);
    }
}
