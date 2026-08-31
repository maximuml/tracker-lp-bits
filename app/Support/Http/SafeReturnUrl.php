<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Support\Http;

/**
 * Filters a user-supplied `returnto` / redirect URL so it can only
 * point to a path inside this application. Absolute URLs, scheme-less
 * URLs (`//host`), protocol-relative URLs (`/\host`), and dangerous
 * schemes (`javascript:`, `data:`) are rejected and replaced with a
 * safe fallback.
 *
 * Lives under {@see Http} because it is a pure,
 * stateless HTTP helper — no DI, no DB, no config.
 */
final class SafeReturnUrl
{
    /**
     * Return a safe, application-relative path derived from $url.
     *
     * @param  string  $url  Raw user input (e.g. `request()->input('returnto')`).
     * @param  string  $fallback  Path to use when $url is unsafe or empty.
     * @return string Always starts with `/` and contains no scheme/host.
     */
    public static function filter(string $url, string $fallback = '/'): string
    {
        $fallback = self::normalise($fallback);

        if ($url === '') {
            return $fallback;
        }

        // Reject anything that starts with a scheme (http:, https:, javascript:, data:, …).
        // `parse_url` would also catch this, but the explicit check is faster
        // and handles `javascript:` which PHP may not always report as a scheme.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            return $fallback;
        }

        // Reject protocol-relative URLs: `//host/path` and `/\host/path`.
        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
            return $fallback;
        }

        // Reject backslash-prefixed variants that browsers may treat as a scheme.
        if (str_starts_with($url, '\\')) {
            return $fallback;
        }

        // parse_url to detect an embedded host (e.g. `//evil` already caught,
        // but `path?x=//evil` should still pass — only reject when host is
        // present at the start of the string).
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $fallback;
        }
        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            return $fallback;
        }

        return self::normalise($url);
    }

    /**
     * Ensure the path starts with a single leading slash and is not empty.
     */
    private static function normalise(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $path;
    }
}
