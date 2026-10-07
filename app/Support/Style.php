<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\StyleRepositoryInterface;
use App\Support\Cache\NexusCache;
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
    public static function cssRow(?NexusCache $cache, int|string $cssId, int|string $defaultId): ?array
    {
        $fromCache = false;
        if (self::$stylesheetRows === null) {
            $cached = $cache?->get('stylesheet_content') ?? false;
            if ($cached !== false) {
                self::$stylesheetRows = is_array($cached) ? $cached : [];
                $fromCache = true;
            } else {
                self::$stylesheetRows = self::styleRepository()->fetchAll();
                $cache?->put('stylesheet_content', self::$stylesheetRows, 95400);
            }
        }

        // The ~26h 'stylesheet_content' blob can predate a stylesheets-table
        // write and be missing rows — revalidate once against the DB rather
        // than silently serving the fallback stylesheet for the whole TTL.
        // ($fromCache implies first call per process, so this runs at most
        // once per request.)
        if ($fromCache && ! array_key_exists((int) $cssId, self::$stylesheetRows)) {
            self::$stylesheetRows = self::styleRepository()->fetchAll();
            $cache?->put('stylesheet_content', self::$stylesheetRows, 95400);
        }

        return self::$stylesheetRows[$cssId] ?? self::$stylesheetRows[$defaultId] ?? null;
    }

    /**
     * Clear the per-process stylesheet row memo (Octane worker reset).
     */
    public static function resetState(): void
    {
        self::$stylesheetRows = null;
    }

    /**
     * Return the stylesheet URI for `$file`, or just the URI if `$file`
     * is empty.
     *
     * Mirrors `get_css_uri()`.
     */
    public static function cssUri(?NexusCache $cache, int|string $cssId, int|string $defaultId, string $file = ''): string
    {
        $row = self::cssRow($cache, $cssId, $defaultId);
        $uri = $row['uri'] ?? self::styleRepository()->uri($defaultId);
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
    public static function addiCode(?NexusCache $cache, int|string $cssId, int|string $defaultId): string
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
        $fallback = self::styleRepository()->highlightColor(5) ?? '';
        if ($userStyleId !== null && $userStyleId > 0) {
            $hltr = self::styleRepository()->highlightColor($userStyleId) ?? '';
            if (! empty($hltr)) {
                return (string) $hltr;
            }
        }

        return (string) $fallback;
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the stylesheet URI.
     */
    public static function cssUriWithContext(string $file = ''): string
    {
        $user = CurrentUser::instance()->get() ?? [];
        $defaultId = self::defaultStylesheetId();

        return self::cssUri(NexusCache::instance(), $user ? CurrentUser::instance()->value('stylesheet') : $defaultId, $defaultId, $file);
    }

    /**
     * Convenience wrapper that reads the current user / default stylesheet
     * from the support context and returns the extra CSS (`addicode`).
     */
    public static function addiCodeWithContext(): SafeHtml
    {
        $user = CurrentUser::instance()->get() ?? [];
        $defaultId = self::defaultStylesheetId();

        return SafeHtml::fromTrustedHtml(self::addiCode(NexusCache::instance(), $user ? CurrentUser::instance()->value('stylesheet') : $defaultId, $defaultId));
    }

    /**
     * Resolve the default stylesheet id from the `main.defstylesheet` setting, falling back
     * to the first stylesheet row (id 3 when the table is empty).
     */
    private static function defaultStylesheetId(): int
    {
        $configured = SiteConfig::current()->main->defStylesheet(0);
        if ($configured !== 0) {
            return $configured;
        }

        return self::styleRepository()->firstId() ?? 3;
    }

    private static function styleRepository(): StyleRepositoryInterface
    {
        return app(StyleRepositoryInterface::class);
    }
}
