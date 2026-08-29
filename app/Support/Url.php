<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\SiteConfig;

/**
 * Request/URL helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 */
final class Url
{
    public static function isSecure(): bool
    {
        if (Environment::isConsole()) {
            return SiteConfig::current()->security->secureLogin();
        }

        return RequestContext::instance()->getRequestSchema() === 'https';
    }

    public static function schemeAndHost(bool $fromConfig = false): string
    {
        if (Environment::isConsole() || $fromConfig) {
            $host = (string) SiteConfig::current()->basic->baseUrl();
        } else {
            $host = RequestContext::instance()->getRequestHost();
        }

        return (self::isSecure() ? 'https' : 'http').'://'.$host;
    }

    public static function baseUrl(): string
    {
        $url = self::schemeAndHost();

        if (! Environment::isConsole()) {
            $requestUri = Input::serverValue('REQUEST_URI', '');
            $pos = strpos($requestUri, '?');
            $url .= $pos !== false ? substr($requestUri, 0, $pos) : $requestUri;
        }

        return trim($url, '/');
    }
}
