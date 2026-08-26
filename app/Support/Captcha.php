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
     * Render the active captcha markup when enabled.
     *
     * Mirrors `show_image_code()`. The `$secret` value is passed by the
     * caller instead of being read from `$_GET` inside the helper.
     *
     * @param  array<string, string>  $labels
     */
    public static function render(string $enabledFlag, array $labels = [], ?string $secret = null): void
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
            'secret' => $secret ?? '',
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
    public static function showImageCode(): void
    {
        $lang_functions = SupportContext::getLangFunctions();
        $iv = (string) SupportContext::getGlobal('iv', '');

        self::render($iv, [
            'row_security_image' => $lang_functions['row_security_image'] ?? '',
            'row_security_challenge' => $lang_functions['row_security_challenge'] ?? '',
            'row_security_code' => $lang_functions['row_security_code'] ?? '',
        ], (string) request()->query('secret', ''));
    }
}
