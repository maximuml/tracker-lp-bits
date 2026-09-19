<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\StyleRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;

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
                self::$stylesheetRows = app(StyleRepository::class)->all();
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
        $uri = $row['uri'] ?? app(StyleRepository::class)->uri($defaultId);
        // ADR 0019: Classic is the hard fallback — a stale defstylesheet or
        // user.stylesheet pointing at a pruned row must never emit a bare
        // 'theme.css' that 404s at the site root.
        $uri = (string) ($uri ?: 'styles/Classic/');

        return $file === '' ? $uri : $uri.$file;
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
        $fallback = app(StyleRepository::class)->highlightColor(5) ?? '';
        if ($userStyleId !== null && $userStyleId > 0) {
            $hltr = app(StyleRepository::class)->highlightColor($userStyleId) ?? '';
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
        $user = app(CurrentUser::class)->get() ?? [];
        $defaultId = self::defaultStylesheetId();

        return self::cssRow(app(LegacyRedisCache::class), $user ? $user['stylesheet'] : $defaultId, $defaultId);
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the stylesheet URI.
     */
    public static function cssUriWithContext(string $file = ''): string
    {
        $user = app(CurrentUser::class)->get() ?? [];
        $defaultId = self::defaultStylesheetId();

        return self::cssUri(app(LegacyRedisCache::class), $user ? $user['stylesheet'] : $defaultId, $defaultId, $file);
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the extra CSS (`addicode`).
     */
    public static function addiCodeWithContext(): SafeHtml
    {
        $user = app(CurrentUser::class)->get() ?? [];
        $defaultId = self::defaultStylesheetId();

        return SafeHtml::fromTrustedHtml(self::addiCode(app(LegacyRedisCache::class), $user ? $user['stylesheet'] : $defaultId, $defaultId));
    }

    /**
     * Convenience wrapper that reads the current user's stylesheet from
     * the support context and returns the highlight CSS.
     */
    public static function highlightColorWithContext(): string
    {
        $user = app(CurrentUser::class)->get() ?? [];

        return self::highlightColor($user ? (int) $user['stylesheet'] : null);
    }

    /**
     * Resolve the default stylesheet id, matching the `defcss` seeding in
     * {@see SettingsSeed}: the `main.defstylesheet` setting, falling back
     * to the first stylesheet row (id 3 when the table is empty).
     */
    private static function defaultStylesheetId(): int
    {
        $configured = SiteConfig::current()->main->defStylesheet(0);
        if ($configured !== 0) {
            return $configured;
        }

        return app(StyleRepository::class)->firstId() ?? 3;
    }
}
