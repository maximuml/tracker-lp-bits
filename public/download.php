<?php

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// When download.php is executed directly by the FPM worker, Symfony's request
// parser treats the script name as the base URL. Build the request manually so
// the path info is /download while preserving query parameters, headers and the
// client IP.
$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$uri = '/download' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
$request = Illuminate\Http\Request::create(
    $uri,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_GET,
    $_COOKIE,
    [],
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
