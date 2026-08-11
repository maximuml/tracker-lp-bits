<?php

namespace App\Support;

/**
 * Legacy site-information helper extracted from `include/globalfunctions.php`.
 *
 * Backs `site_info()`. Centralises the small bundle of basic-site metadata
 * that legacy pages build on demand.
 */
final class Site
{
    /**
     * @return array<string, string>
     */
    public static function info(): array
    {
        return [
            'site_name' => \App\Support\Config\SiteConfig::current()->basic->siteName(),
            'base_url' => Url::schemeAndHost(),
        ];
    }
}
