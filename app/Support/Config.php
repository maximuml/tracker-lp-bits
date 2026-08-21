<?php

namespace App\Support;

/**
 * Legacy runtime config loader extracted from `include/globalfunctions.php`.
 *
 * Mirrors `nexus_config()`. In a Laravel context it delegates to the
 * `config()` helper; in the legacy bootstrap it loads a fixed allow-list
 * of config files from `config/` and resolves dotted keys with
 * {@see Arrays::get()}.
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $configs = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! (defined('IN_NEXUS') && IN_NEXUS)) {
            return config($key, $default);
        }

        if (self::$configs === null) {
            self::loadLegacyConfigs();
        }

        return Arrays::get(self::$configs ?? [], $key, $default);
    }

    private static function loadLegacyConfigs(): void
    {
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2).'/';
        $files = ['nexus', 'emoji', 'captcha', 'clickhouse'];

        foreach ($files as $prefix) {
            $file = $root.'config/'.$prefix.'.php';
            if (! file_exists($file)) {
                continue;
            }
            self::$configs[$prefix] = require $file;
        }
    }
}
