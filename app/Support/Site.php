<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\SiteConfig;

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
            'site_name' => SiteConfig::current()->basic->siteName(),
            'base_url' => Url::schemeAndHost(),
        ];
    }
}
