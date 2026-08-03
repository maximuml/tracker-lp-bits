<?php

/**
 * Legacy POST handler for the login form.
 *
 * Forwards the submitted credentials to the Laravel /login route so
 * existing forms (and old bookmarks) keep working.
 */

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$uri = '/login' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);

$request = Illuminate\Http\Request::create(
    $uri,
    'POST',
    $_POST,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
