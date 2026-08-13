<?php

namespace App\Support;

use Illuminate\Http\Request;
use Nexus\Nexus;
use Nexus\Plugin\Hook;
use Nexus\Plugin\Plugin;

/**
 * One-stop legacy bootstrap for web wrappers and console commands.
 *
 * Replaces the responsibilities of `include/core.php` and `include/config.php`
 * by wiring the cache, Eloquent/legacy DB, settings, language, user login and
 * plugin system into the static `SupportContext` instead of `$GLOBALS`.
 */
final class LegacyBootstrap
{
    public static function boot(?Request $request = null, string $rootpath = ''): void
    {
        defined('IN_NEXUS') || define('IN_NEXUS', false);

        self::resetAndCapture($request);

        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 0);

        self::bootNexus();
        self::bootCache($rootpath);
        self::bootDatabase();
        self::bootTimezone();
        self::bootSettings();
        self::bootLanguage($rootpath);
        self::bootUser($request);
        self::bootPlugins($rootpath);
    }

    public static function bootConsole(string $rootpath = ''): void
    {
        defined('IN_NEXUS') || define('IN_NEXUS', false);

        self::resetAndCapture(null);

        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', 0);

        self::bootNexus();
        self::bootCache($rootpath);
        self::bootDatabase();
        self::bootTimezone();
        self::bootSettings();
        self::bootLanguage($rootpath);
        self::bootPlugins($rootpath);
    }

    private static function resetAndCapture(?Request $request): void
    {
        SupportContext::reset();
        if ($request !== null) {
            SupportContext::fromRequest($request);
        }
    }

    private static function bootNexus(): void
    {
        if (defined('RUNNING_IN_OCTANE') && RUNNING_IN_OCTANE) {
            return;
        }

        Nexus::flush();
        Nexus::boot();
    }

    private static function bootCache(string $rootpath): void
    {
        $Cache = new \App\Support\Cache\LegacyRedisCache();
        $Cache->setLanguageFolderArray(get_langfolder_list());
        SupportContext::setCache($Cache);
    }

    private static function bootDatabase(): void
    {
        if (defined('IN_NEXUS') && IN_NEXUS) {
            $dbConfig = Config::get('nexus.database');
            $config = $dbConfig['connections'][$dbConfig['default']];
            \Nexus\Database\NexusDB::bootEloquent($config);
        }
        \Nexus\Database\NexusDB::customModel();
    }

    private static function bootTimezone(): void
    {
        ini_set('date.timezone', nexus_config('nexus.timezone'));
    }

    private static function bootSettings(): void
    {
        SettingsSeed::seed();
    }

    private static function bootLanguage(string $rootpath): void
    {
        $script = nexus()->getScript();
        if (in_array($script, ['announce', 'scrape'], true)) {
            return;
        }

        $langFile = $rootpath . get_langfile_path('functions.php');
        $langFunctions = [];
        if (is_file($langFile)) {
            require $langFile;
            if (isset($lang_functions) && is_array($lang_functions)) {
                $langFunctions = $lang_functions;
            }
        }
        SupportContext::setLangFunctions($langFunctions);
    }

    private static function bootUser(?Request $request): void
    {
        if ($request === null) {
            return;
        }

        $script = nexus()->getScript();
        if (in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            return;
        }

        defined('TIMENOW') || define('TIMENOW', time());

        checkGuestVisit();
    }

    private static function bootPlugins(string $rootpath): void
    {
        defined('TIMENOW') || define('TIMENOW', time());

        $hook = app(Hook::class);
        $plugin = app(Plugin::class);
        SupportContext::setGlobal('hook', $hook);
        SupportContext::setGlobal('plugin', $plugin);
        $plugin->start();
    }
}
