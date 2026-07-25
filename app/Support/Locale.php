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
        $result = NexusDB::getInstance()->query(
            'SELECT site_lang_folder FROM language LEFT JOIN users ON language.id = users.lang '
            . 'WHERE language.site_lang = 1 AND users.id = ' . sqlesc($userId) . ' LIMIT 1'
        );
        $row = NexusDB::getInstance()->fetchAssoc($result);

        return $row['site_lang_folder'] ?? 'en';
    }

    /**
     * Return the `site_lang_folder` for the given language id, or
     * `$default` when the id is unknown.
     *
     * Mirrors `validlang()`.
     */
    public static function folderForId(int|string $langId, string $default = 'en'): string
    {
        $result = NexusDB::getInstance()->query(
            'SELECT site_lang_folder FROM language WHERE site_lang = 1 AND id = ' . sqlesc($langId) . ' LIMIT 1'
        );
        $row = NexusDB::getInstance()->fetchAssoc($result);

        return $row['site_lang_folder'] ?? $default;
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
     * Return the language id for the given folder name.
     *
     * Mirrors `get_langid_from_langcookie()`.
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
        $result = NexusDB::getInstance()->query(
            'SELECT id FROM language WHERE site_lang_folder=' . sqlesc($langFolder) . ' AND site_lang=1'
        );
        $row = NexusDB::getInstance()->fetchAssoc($result);

        return $row['id'] ?? 6;
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
}
