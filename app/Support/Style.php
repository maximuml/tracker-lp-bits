<?php

namespace App\Support;

use App\Repositories\StyleRepository;

/**
 * Legacy stylesheet helpers extracted from `include/functions.php`.
 *
 * Backs the style proxy functions (`get_css_row`, `get_css_uri`,
 * `get_font_css_uri`, `get_style_addicode`, `get_style_highlight`).
 *
 * The `$cache` object is the legacy global `Cache` instance and is passed
 * in by the wrapper so this class does not depend on global state at
 * call sites.
 */
final class Style
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $stylesheetRows = null;

    /**
     * Return the stylesheet row for the given id, falling back to
     * `$defaultId`.
     *
     * Mirrors `get_css_row()`.
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function cssRow(mixed $cache, int|string $cssId, int|string $defaultId): ?array
    {
        if (self::$stylesheetRows === null) {
            $cached = is_object($cache) && method_exists($cache, 'get_value') ? $cache->get_value('stylesheet_content') : false;
            if ($cached !== false) {
                self::$stylesheetRows = is_array($cached) ? $cached : [];
            } else {
                self::$stylesheetRows = StyleRepository::all();
                if (is_object($cache) && method_exists($cache, 'cache_value')) {
                    $cache->cache_value('stylesheet_content', self::$stylesheetRows, 95400);
                }
            }
        }

        return self::$stylesheetRows[$cssId] ?? self::$stylesheetRows[$defaultId] ?? null;
    }

    /**
     * Return the stylesheet URI for `$file`, or just the URI if `$file`
     * is empty.
     *
     * Mirrors `get_css_uri()`.
     */
    public static function cssUri(mixed $cache, int|string $cssId, int|string $defaultId, string $file = ''): string
    {
        $row = self::cssRow($cache, $cssId, $defaultId);
        $uri = $row['uri'] ?? StyleRepository::uri($defaultId);

        return $file === '' ? (string) $uri : (string) $uri.$file;
    }

    /**
     * Return the URI for the per-user font css file.
     *
     * Mirrors `get_font_css_uri()`.
     */
    public static function fontCssUri(?string $fontSize): string
    {
        $file = match ($fontSize) {
            'large' => 'largefont.css',
            'small' => 'smallfont.css',
            default => 'mediumfont.css',
        };

        return 'styles/'.$file;
    }

    /**
     * Return the extra CSS (`addicode`) for the current stylesheet row.
     *
     * Mirrors `get_style_addicode()`.
     */
    public static function addiCode(mixed $cache, int|string $cssId, int|string $defaultId): string
    {
        $row = self::cssRow($cache, $cssId, $defaultId);

        return (string) ($row['addicode'] ?? '');
    }

    /**
     * Return the highlight row CSS for the current user, falling back to
     * the stylesheet with id `5`.
     *
     * Mirrors `get_style_highlight()`.
     */
    public static function highlightColor(?int $userStyleId): string
    {
        $fallback = StyleRepository::highlightColor(5) ?? '';
        if ($userStyleId !== null && $userStyleId > 0) {
            $hltr = StyleRepository::highlightColor($userStyleId) ?? '';
            if (! empty($hltr)) {
                return (string) $hltr;
            }
        }

        return (string) $fallback;
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns a stylesheet row.
     *
     * @return array<string, mixed>|null
     */
    public static function cssRowWithContext(): ?array
    {
        $user = SupportContext::getUser() ?? [];
        $defaultId = (int) SupportContext::getGlobal('defcss', 0);

        return self::cssRow(SupportContext::getCache(), $user ? $user['stylesheet'] : $defaultId, $defaultId);
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the stylesheet URI.
     */
    public static function cssUriWithContext(string $file = ''): string
    {
        $user = SupportContext::getUser() ?? [];
        $defaultId = (int) SupportContext::getGlobal('defcss', 0);

        return self::cssUri(SupportContext::getCache(), $user ? $user['stylesheet'] : $defaultId, $defaultId, $file);
    }

    /**
     * Convenience wrapper that reads the current user's font size from
     * the support context and returns the font CSS URI.
     */
    public static function fontCssUriWithContext(): string
    {
        $user = SupportContext::getUser() ?? [];

        return self::fontCssUri($user['fontsize'] ?? null);
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the extra CSS (`addicode`).
     */
    public static function addiCodeWithContext(): string
    {
        $user = SupportContext::getUser() ?? [];
        $defaultId = (int) SupportContext::getGlobal('defcss', 0);

        return self::addiCode(SupportContext::getCache(), $user ? $user['stylesheet'] : $defaultId, $defaultId);
    }

    /**
     * Convenience wrapper that reads the current user's stylesheet from
     * the support context and returns the highlight CSS.
     */
    public static function highlightColorWithContext(): string
    {
        $user = SupportContext::getUser() ?? [];

        return self::highlightColor($user ? (int) $user['stylesheet'] : null);
    }
}
