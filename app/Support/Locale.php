<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\Locale as LocaleMiddleware;
use App\Models\Language;
use App\Models\Setting;
use App\Repositories\LanguageRepository;
use Nexus\Nexus;

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
        return array_values(array_map('strval', Language::listAvailable()));
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
        return app(LanguageRepository::class)->getUserFolder((int) $userId);
    }

    /**
     * Return the `site_lang_folder` for the given language id, or
     * `$default` when the id is unknown.
     *
     * Mirrors `validlang()`.
     */
    public static function folderForId(int|string $langId, string $default = 'en'): string
    {
        return app(LanguageRepository::class)->getFolderForId((int) $langId, $default);
    }

    /**
     * Context-aware wrapper for {@see folderForId()}.
     */
    public static function folderForIdWithContext(int|string $langId): string
    {
        return self::folderForId($langId, (string) app(Globals::class)->get('deflang', 'en'));
    }

    /**
     * Build the relative language file path.
     *
     * Mirrors `get_langfile_path()`. Also mutates the legacy `$CURLANGDIR`
     * global because callers rely on it being set as a side effect.
     */
    public static function scriptFilePath(string $scriptName = '', bool $target = false, string $langFolder = ''): string
    {
        $CURLANGDIR = self::folderFromCookie(Input::cookieValue('c_lang_folder'));
        app(Globals::class)->set('CURLANGDIR', $CURLANGDIR);
        if ($langFolder === '') {
            $langFolder = $CURLANGDIR;
        }

        return self::filePath($langFolder, $scriptName, Input::serverValue('SCRIPT_NAME', ''), $target);
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
            $scriptName = substr((string) strrchr($serverScriptName, '/'), 1) ?: '';
        }

        return 'lang/'.$folder.'/lang_'.$scriptName;
    }

    /**
     * Set the `c_lang_folder` cookie.
     *
     * Mirrors `set_langfolder_cookie()`.
     */
    public static function setFolderCookie(string $folder, int $expires = 0x7FFFFFFF): void
    {
        if ($expires !== 0x7FFFFFFF) {
            $expires = time() + $expires;
        }

        setcookie('c_lang_folder', $folder, $expires, '/', '', Url::isSecure(), true);
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
            $lang = self::folderFromCookie(Input::cookieValue('c_lang_folder'));
        }

        return self::idFromFolder($lang);
    }

    /**
     * Return the language id for the given folder name.
     */
    public static function idFromFolder(string $lang): int
    {
        return app(LanguageRepository::class)->getIdFromFolder($lang);
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
        return app(LanguageRepository::class)->getLanguageList($type, $enabled);
    }

    /**
     * Return the language id for the current language folder, defaulting
     * to English (6) if none is found.
     *
     * Mirrors `get_guest_lang_id()`.
     */
    public static function guestId(string $langFolder): int
    {
        return app(LanguageRepository::class)->getGuestId($langFolder);
    }

    /**
     * Context-aware wrapper for {@see guestId()}.
     */
    public static function guestIdWithContext(): int
    {
        return self::guestId((string) app(Globals::class)->get('CURLANGDIR', ''));
    }

    /**
     * Legacy `nexus_trans()` helper. Delegates to the Nexus translator.
     *
     * @param  array<string, mixed>  $replace
     */
    public static function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return Nexus::trans($key, $replace, $locale);
    }

    /**
     * Return the locale (e.g. `zh-CN`, `en`) for the given user id.
     *
     * Mirrors `get_user_locale()`.
     */
    public static function userLocale(int $uid): string
    {
        return app(LanguageRepository::class)->getUserLocale($uid);
    }
}
