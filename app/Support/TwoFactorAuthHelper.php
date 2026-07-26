<?php

namespace App\Support;

use RobThree\Auth\Providers\Qr\GoogleChartsQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

class TwoFactorAuthHelper
{
    private static ?TwoFactorAuth $tfa = null;

    private static function getTfa(): TwoFactorAuth
    {
        if (is_null(self::$tfa)) {
            self::$tfa = new TwoFactorAuth(new GoogleChartsQrCodeProvider(), get_setting('basic.SITENAME'));
        }
        return self::$tfa;
    }

    public static function createSecret(int $bits = 80): string
    {
        return self::getTfa()->createSecret($bits);
    }

    public static function verifyCode(string $secret, string $code): bool
    {
        return self::getTfa()->verifyCode($secret, $code);
    }

    public static function qrCodeUrl(string $label, string $secret, int $size = 200): string
    {
        $provider = new GoogleChartsQrCodeProvider();
        return $provider->getUrl(self::getTfa()->getQRText($label, $secret), $size);
    }
}
