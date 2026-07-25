<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Legacy site-information helper extracted from `include/globalfunctions.php`.
 *
 * Backs `site_info()`. Centralises the small bundle of basic-site metadata
 * that legacy pages build on demand.
 */
final class Site
{
    public static function info(): array
    {
        $setting = Setting::get('basic');

        return [
            'site_name' => $setting['SITENAME'] ?? '',
            'base_url' => Url::schemeAndHost(),
        ];
    }
}
