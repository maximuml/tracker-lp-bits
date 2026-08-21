<?php

use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Services\Captcha\Drivers\RecaptchaV2CaptchaDriver;
use App\Services\Captcha\Drivers\TurnstileCaptchaDriver;
use App\Support\Env;

return [
    'default' => Env::get('CAPTCHA_DRIVER', 'image'),

    'drivers' => [
        'image' => [
            'class' => ImageCaptchaDriver::class,
        ],

        'cloudflare_turnstile' => [
            'class' => TurnstileCaptchaDriver::class,
            'site_key' => Env::get('TURNSTILE_SITE_KEY', null),
            'secret_key' => Env::get('TURNSTILE_SECRET_KEY', null),
            'theme' => Env::get('TURNSTILE_THEME', 'auto'),
            'size' => Env::get('TURNSTILE_SIZE', 'auto'),
        ],

        'google_recaptcha_v2' => [
            'class' => RecaptchaV2CaptchaDriver::class,
            'site_key' => Env::get('RECAPTCHA_SITE_KEY', null),
            'secret_key' => Env::get('RECAPTCHA_SECRET_KEY', null),
            'theme' => Env::get('RECAPTCHA_THEME', 'light'),
            'size' => Env::get('RECAPTCHA_SIZE', 'normal'),
        ],
    ],

    'attendance' => [
        'enabled' => Env::get('CAPTCHA_ATTENDANCE_ENABLED', true),
    ],
];
