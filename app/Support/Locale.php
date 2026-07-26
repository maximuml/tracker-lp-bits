<?php

namespace App\Support;

use App\Http\Middleware\Locale as LocaleMiddleware;
use App\Models\Language;
use App\Models\Setting;
use Nexus\Database\NexusDB;

/**
 * Legacy locale helpers extracted from `include/functions.php`.
 *
 * Backs `get_langfolder_cookie()`, `get_user_lang()` and
 * `get_langfile_path()` with the cookie / DB / path-building parts
 * separated. The global `$CURLANGDIR` mutation stays in the legacy
 * proxy so the class itself is side-effect free.
 */
final class Locale
{
    /**
     * Pick the active language folder from the `c_lang_folder` cookie,
     * falling back to the configured default language.
     *
     * Mirrors `get_langfolder_cookie()`.
     */
    /**
     * Return the list of available language folders.
     *
     * Mirrors `get_langfolder_list()`.
     *
     * @return array<int, string>
     */
    public static function available(): array
    {
        return Language::listAvailable();
    }

    public static function folderFromCookie(?string $cookieValue, bool $transToLocale = false): string
    {
        $default = Setting::getDefaultLang();
        $lang = '';

        if ($cookieValue === null || $cookieValue === '') {
            $lang = $default;
        } else {
            $allowed = Language::listAvailable();
            $enabled = Language::listEnabled();
            foreach ($allowed as $folder) {
                if ($folder === $cookieValue && in_array($folder, $enabled, true)) {
                    $lang = $cookieValue;
                    break;
                }
            }
        }

        if ($lang === '') {
            $lang = $default;
        }

        if ($transToLocale) {
            return LocaleMiddleware::$languageMaps[$lang] ?? 'en';
        }

        return $lang;
    }

    /**
     * Return the `site_lang_folder` for the given user, or `'en'`.
     *
     * Mirrors `get_user_lang()`.
     */
    public static function userFolder(int|string $userId): string
    {
        $folder = \App\Models\Language::query()
            ->select('language.site_lang_folder')
            ->leftJoin('users', 'language.id', '=', 'users.lang')
            ->where('language.site_lang', 1)
            ->where('users.id', $userId)
            ->value('site_lang_folder');

        return $folder ?? 'en';
    }

    /**
     * Return the `site_lang_folder` for the given language id, or
     * `$default` when the id is unknown.
     *
     * Mirrors `validlang()`.
     */
    public static function folderForId(int|string $langId, string $default = 'en'): string
    {
        return \App\Models\Language::query()
            ->where('site_lang', 1)
            ->where('id', $langId)
            ->value('site_lang_folder') ?? $default;
    }

    /**
     * Build the relative language file path.
     *
     * Mirrors `get_langfile_path()`. Also mutates the legacy `$CURLANGDIR`
     * global because callers rely on it being set as a side effect.
     */
    public static function scriptFilePath(string $scriptName = '', bool $target = false, string $langFolder = ''): string
    {
        global $CURLANGDIR;
        $CURLANGDIR = self::folderFromCookie($_COOKIE['c_lang_folder'] ?? null);
        if ($langFolder === '') {
            $langFolder = $CURLANGDIR;
        }

        return self::filePath($langFolder, $scriptName, $_SERVER['SCRIPT_NAME'] ?? '', $target);
    }

    /**
     * Build the relative language file path.
     *
     * Mirrors `get_langfile_path()` without mutating `$CURLANGDIR`.
     */
    public static function filePath(string $langFolder, string $scriptName = '', string $serverScriptName = '', bool $target = false): string
    {
        $folder = $target ? '_target' : $langFolder;
        if ($scriptName === '') {
            $scriptName = substr(strrchr($serverScriptName, '/'), 1) ?: '';
        }

        return 'lang/' . $folder . '/lang_' . $scriptName;
    }

    /**
     * Set the `c_lang_folder` cookie.
     *
     * Mirrors `set_langfolder_cookie()`.
     */
    public static function setFolderCookie(string $folder, int $expires = 0x7fffffff): void
    {
        if ($expires !== 0x7fffffff) {
            $expires = time() + $expires;
        }

        setcookie('c_lang_folder', $folder, $expires, '/', '', false, true);
    }

    /**
     * Return the language id for the given folder name, falling back to
     * the current cookie folder when none is provided.
     *
     * Mirrors `get_langid_from_langcookie()`.
     */
    public static function idFromCookie(string $lang = ''): int
    {
        if ($lang === '') {
            $lang = self::folderFromCookie($_COOKIE['c_lang_folder'] ?? null);
        }

        return self::idFromFolder($lang);
    }

    /**
     * Return the language id for the given folder name.
     */
    public static function idFromFolder(string $lang): int
    {
        $row = Language::query()
            ->where('site_lang', 1)
            ->where('site_lang_folder', $lang)
            ->orderBy('id')
            ->first();

        return (int) ($row->id ?? 0);
    }

    /**
     * Return the list of languages usable for a given scope.
     *
     * Mirrors `langlist($type, $enabled)`.
     *
     * @param  string  $type  e.g. 'rule_lang', 'site_lang'
     * @return array<int, array<string, mixed>>
     */
    public static function languageList(string $type, ?bool $enabled = null): array
    {
        $cacheKey = $type . '_lang_list';

        return NexusDB::remember($cacheKey, 600, function () use ($type, $enabled) {
            $query = Language::query()->where($type, 1);
            if ($enabled !== null) {
                $query->whereIn('site_lang_folder', Language::listEnabled(true));
            }

            return $query->get()->toArray();
        });
    }

    /**
     * Return the language id for the current language folder, defaulting
     * to English (6) if none is found.
     *
     * Mirrors `get_guest_lang_id()`.
     */
    public static function guestId(string $langFolder): int
    {
        return (int) (\App\Models\Language::query()
            ->where('site_lang_folder', $langFolder)
            ->where('site_lang', 1)
            ->value('id') ?? 6);
    }

    /**
     * Legacy `nexus_trans()` helper. Delegates to the Nexus translator.
     *
     * @param  array<string, string>  $replace
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Nexus\Nexus::trans($key, $replace, $locale);
    }

    /**
     * Return the locale (e.g. `zh-CN`, `en`) for the given user id.
     *
     * Mirrors `get_user_locale()`.
     */
    public static function userLocale(int $uid): string
    {
        $folder = \App\Models\Language::query()
            ->select('language.site_lang_folder')
            ->join('users', 'users.lang', '=', 'language.id')
            ->where('users.id', $uid)
            ->value('site_lang_folder');

        if (empty($folder)) {
            return 'en';
        }

        return LocaleMiddleware::$languageMaps[$folder] ?? $folder;
    }
}
