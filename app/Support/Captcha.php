<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Captcha\CaptchaManager;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Support\Config\SiteConfig;

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
            self::$manager = new CaptchaManager;
        }

        return self::$manager;
    }

    /**
     * Issue an image captcha code.
     *
     * Mirrors `image_code()`.
     */
    public static function imageCode(): mixed
    {
        $driver = self::manager()->driver('image');

        if (! method_exists($driver, 'issue')) {
            throw new \RuntimeException('Image captcha driver is unavailable.');
        }

        return $driver->issue();
    }

    /**
     * Row template for captcha markup: legacy `<tr>` for table hosts or
     * `nx-fhead`/`nx-fcell` divs for `.nx-fgrid` hosts.
     */
    public static function rowTemplate(string $layout): string
    {
        return $layout === 'grid'
            ? '<div class="nx-fhead">%s</div><div class="nx-fcell">%s</div>'
            : '<tr><td class="rowhead">%s</td><td align="left">%s</td></tr>';
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

        $manager = self::manager();
        $driver = $manager->driver();

        if (! $driver->isEnabled()) {
            return;
        }

        $labelKey = $driver instanceof ImageCaptchaDriver
            ? 'row_security_image'
            : 'row_security_challenge';

        $markup = $driver->render([
            'labels' => [
                'image' => __('legacy/functions.'.$labelKey),
                'code' => __('legacy/functions.row_security_code'),
            ],
            'secret' => $secret ?? '',
            'layout' => $layout,
        ]);

        if ($markup !== '') {
            echo $markup;
        }
    }

    /**
     * Verify an image captcha response. Backs the legacy `check_code()` helper.
     */
    public static function checkCode(
        string $imagehash,
        string $imagestring,
        string $where = 'signup.php',
        bool $maxattemptlog = false,
        bool $head = true,
    ): bool {
        return LegacyAuth::checkCode($imagehash, $imagestring, $where, $maxattemptlog, $head, LegacyAuthContext::fromSupportContext());
    }

    /**
     * Render the active captcha markup when enabled. Backs the legacy `show_image_code()` helper.
     */
    public static function showImageCode(string $layout = 'tr'): void
    {
        $iv = SiteConfig::current()->security->captchaRequired() ? 'yes' : 'no';

        self::render($iv, (string) request()->query('secret', ''), $layout);
    }
}
