<?php

/**
 * Legacy entry point for logout.
 *
 * Forwards the request to the Laravel /logout route so both /logout
 * and the old /logout.php URL clear the auth cookie and session.
 */

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$uri = '/logout' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);

$request = Illuminate\Http\Request::create(
    $uri,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_GET,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
