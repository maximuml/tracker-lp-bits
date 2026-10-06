<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Captcha\CaptchaManager;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Support\Config\SiteConfig;
use Illuminate\Container\Container;

/**
 * Legacy captcha helpers extracted from `include/functions.php`.
 *
 * Backs `captcha_manager()`, `image_code()` and `show_image_code()`.
 */
final class Captcha
{
    private static ?CaptchaManager $manager = null;

    /**
     * Return the shared CaptchaManager instance.
     *
     * Mirrors `captcha_manager()`.
     */
    public static function manager(): CaptchaManager
    {
        if (self::$manager === null) {
            self::$manager = new CaptchaManager(Container::getInstance());
        }

        return self::$manager;
    }

    /**
     * Render the active captcha markup when enabled.
     *
     * Mirrors `show_image_code()`. The `$secret` value is passed by the
     * caller instead of being read from `$_GET` inside the helper.
     */
    public static function render(string $enabledFlag, ?string $secret = null, string $layout = 'tr'): void
    {
        if ($enabledFlag !== 'yes') {
            return;
        }

        $markup = self::markup($secret ?? '', $layout);

        if ($markup !== '') {
            echo $markup;
        }
    }

    /**
     * Return the active captcha markup instead of echoing it — for callers
     * that pass markup into a view rather than printing inline.
     * Defaults mirror `showImageCode()` (iv flag + secret from the query).
     */
    public static function renderHtml(?string $enabledFlag = null, ?string $secret = null, string $layout = 'tr'): string
    {
        $enabledFlag ??= SiteConfig::current()->security->captchaRequired() ? 'yes' : 'no';
        if ($enabledFlag !== 'yes') {
            return '';
        }

        return self::markup($secret ?? (string) request()->query('secret', ''), $layout);
    }

    private static function markup(string $secret, string $layout): string
    {
        $driver = self::manager()->driver();

        if (! $driver->isEnabled()) {
            return '';
        }

        $labelKey = $driver instanceof ImageCaptchaDriver
            ? 'row_security_image'
            : 'row_security_challenge';

        return $driver->render([
            'labels' => [
                'image' => __('legacy/functions.'.$labelKey),
                'code' => __('legacy/functions.row_security_code'),
            ],
            'secret' => $secret,
            'layout' => $layout,
        ]);
    }

    /**
     * Verify an image captcha response. Backs the legacy `check_code()` helper.
     */
    public static function checkCode(
        string $imagehash,
        string $imagestring,
        string $where = '/signup',
        bool $maxattemptlog = false,
        bool $head = true,
    ): bool {
        return LegacyAuth::checkCode($imagehash, $imagestring, $where, $maxattemptlog, $head, LegacyAuthContext::fromSupportContext());
    }
}
