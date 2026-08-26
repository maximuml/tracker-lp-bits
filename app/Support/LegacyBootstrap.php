<?php

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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
        // LegacyRedisCache is now registered as a singleton in
        // AppServiceProvider::register(). Just resolve it to trigger
        // the connection + language folder setup.
        app(LegacyRedisCache::class);
    }

    private static function bootDatabase(): void
    {
        if (class_exists(Sanctum::class)) {
            Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        }
    }

    private static function bootTimezone(): void
    {
        ini_set('date.timezone', Config::get('nexus.timezone', null));
    }

    private static function bootSettings(): void
    {
        SettingsSeed::seed();
    }

    private static function bootLanguage(string $rootpath): void
    {
        $script = Nexus::instance()->getScript();
        if (in_array($script, ['announce', 'scrape'], true)) {
            return;
        }

        $langFile = $rootpath.Locale::scriptFilePath((string) 'functions.php', (bool) false, (string) '');
        $langFunctions = [];
        if (is_file($langFile)) {
            require $langFile;
            if (isset($lang_functions) && is_array($lang_functions)) {
                $langFunctions = $lang_functions;
            }
        }
        app(Globals::class)->set('lang_functions', $langFunctions);
    }

    private static function bootUser(?Request $request): void
    {
        if ($request === null) {
            return;
        }

        $script = Nexus::instance()->getScript();
        if (in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            return;
        }

        defined('TIMENOW') || define('TIMENOW', time());

        SiteAccess::checkGuestVisit();
    }

    private static function bootPlugins(string $rootpath): void
    {
        defined('TIMENOW') || define('TIMENOW', time());

        $hook = app(Hook::class);
        $plugin = app(Plugin::class);
        app(Globals::class)->set('hook', $hook);
        app(Globals::class)->set('plugin', $plugin);
        $plugin->start();
    }
}
