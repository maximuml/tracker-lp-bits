<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$action = $_GET['action'] ?? '';
$imagehash = $_GET['imagehash'] ?? '';

if ($action !== 'regimage') {
    http_response_code(404);
    echo 'Invalid captcha action';
    return;
}

$driver = captcha_manager()->driver('image');

if (!method_exists($driver, 'outputImage')) {
    http_response_code(404);
    echo 'Captcha driver does not support image rendering';
    return;
}

$driver->outputImage($imagehash);
