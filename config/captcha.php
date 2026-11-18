<?php

use App\Services\Captcha\Drivers\ImageCaptchaDriver;
use App\Support\Env;

return [
    'default' => Env::get('CAPTCHA_DRIVER', 'image'),

    'drivers' => [
        'image' => [
            'class' => ImageCaptchaDriver::class,
        ],
    ],

    'attendance' => [
        'enabled' => Env::get('CAPTCHA_ATTENDANCE_ENABLED', true),
    ],
];
