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

}
