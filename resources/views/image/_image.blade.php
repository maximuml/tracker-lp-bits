<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$action = \request()->query('action') ?? '';
$imagehash = \request()->query('imagehash') ?? '';

if ($action !== 'regimage') {
    http_response_code(404);
    echo 'Invalid captcha action';
    return;
}

$driver = \App\Support\Captcha::manager()->driver('image');

if (!method_exists($driver, 'outputImage')) {
    http_response_code(404);
    echo 'Captcha driver does not support image rendering';
    return;
}

$driver->outputImage($imagehash);
