<?php

namespace App\Support;

use App\Services\Captcha\CaptchaManager;
use App\Services\Captcha\Drivers\ImageCaptchaDriver;

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
            self::$manager = new CaptchaManager();
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
     * Render the active captcha markup when enabled.
     *
     * Mirrors `show_image_code()`.
     */
    /**
     * @param  array<string, string>  $labels
     */
    public static function render(string $enabledFlag, array $labels = []): void
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
                'image' => $labels[$labelKey] ?? $labels['row_security_image'] ?? '',
                'code' => $labels['row_security_code'] ?? '',
            ],
            'secret' => $_GET['secret'] ?? '',
        ]);

        if ($markup !== '') {
            echo $markup;
        }
    }
}
