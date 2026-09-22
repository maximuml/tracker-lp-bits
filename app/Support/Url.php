<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\SiteConfig;

/**
 * Request/URL helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration.
 */
final class Url
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public static function isSecure(): bool
    {
        if (Environment::isConsole()) {
            return SiteConfig::current()->security->secureLogin();
        }

        return RequestContext::instance()->getRequestSchema() === 'https';
    }

    /**
     * Canonical form for a stored site/tracker URL: `scheme://host[:port][/path]`
     * with no trailing slash. Accepts legacy host-only values (`example.com`,
     * `example.com/announce.php`, `[::1]:8080`) — a missing scheme defaults to
     * the current request scheme. Returns null when the value cannot be a
     * valid http(s) URL.
     */
    public static function normalize(string $raw, ?bool $secure = null): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '//')) {
            $raw = substr($raw, 2);
        }

        if (str_contains($raw, '://')) {
            $withScheme = $raw;
        } else {
            $scheme = ($secure ?? self::isSecure()) ? 'https' : 'http';
            $withScheme = $scheme.'://'.$raw;
        }

        $parts = parse_url($withScheme);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        $host = (string) ($parts['host'] ?? '');
        if (! self::isValidHost($host)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $path = rtrim($path, '/');

        $hostOut = str_contains($host, ':') ? '['.trim($host, '[]').']' : strtolower($host);

        return $scheme.'://'.$hostOut.($port !== null ? ':'.$port : '').$path;
    }

    /**
     * Like {@see normalize()} but returns an empty string instead of null, for
     * call sites that previously concatenated `protocolPrefix.$host` and
     * produced `http://http://...` when the stored value already had a scheme.
     */
    public static function absolute(string $raw, ?bool $secure = null): string
    {
        return self::normalize($raw, $secure) ?? '';
    }

    /**
     * Public site root from configuration (`basic.BASEURL`), always with a
     * scheme. Config-only on purpose: unlike `Setting::getBaseUrl()` this
     * never falls back to the request Host header, so email links cannot be
     * poisoned by a spoofed Host. Empty string when nothing usable is set.
     */
    public static function siteBase(): string
    {
        return self::absolute((string) SiteConfig::current()->basic->baseUrl());
    }

    public static function schemeAndHost(bool $fromConfig = false): string
    {
        if (Environment::isConsole() || $fromConfig) {
            $host = (string) SiteConfig::current()->basic->baseUrl();
        } else {
            $host = RequestContext::instance()->getRequestHost();
        }

        // The stored value may already contain a scheme (canonical format)
        // or be host-only (legacy); normalize handles both.
        return self::absolute($host);
    }

    private static function isValidHost(string $host): bool
    {
        if ($host === '' || preg_match('/[\s\x00-\x1f\x7f]/', $host)) {
            return false;
        }

        $bare = trim($host, '[]');
        if (filter_var($bare, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-_]*[a-z0-9])?)*\.?$/i', $host);
    }
}
