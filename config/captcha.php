<?php

return [
    'default' => \App\Support\Env::get('CAPTCHA_DRIVER', 'image'),

    'drivers' => [
        'image' => [
            'class' => \App\Services\Captcha\Drivers\ImageCaptchaDriver::class,
        ],

        'cloudflare_turnstile' => [
            'class' => \App\Services\Captcha\Drivers\TurnstileCaptchaDriver::class,
            'site_key' => \App\Support\Env::get('TURNSTILE_SITE_KEY', null),
            'secret_key' => \App\Support\Env::get('TURNSTILE_SECRET_KEY', null),
            'theme' => \App\Support\Env::get('TURNSTILE_THEME', 'auto'),
            'size' => \App\Support\Env::get('TURNSTILE_SIZE', 'auto'),
        ],

        'google_recaptcha_v2' => [
            'class' => \App\Services\Captcha\Drivers\RecaptchaV2CaptchaDriver::class,
            'site_key' => \App\Support\Env::get('RECAPTCHA_SITE_KEY', null),
            'secret_key' => \App\Support\Env::get('RECAPTCHA_SECRET_KEY', null),
            'theme' => \App\Support\Env::get('RECAPTCHA_THEME', 'light'),
            'size' => \App\Support\Env::get('RECAPTCHA_SIZE', 'normal'),
        ],
    ],

    'attendance' => [
        'enabled' => \App\Support\Env::get('CAPTCHA_ATTENDANCE_ENABLED', true),
    ],
];
