<?php

/**
 * Legacy entry point for the login page.
 *
 * This thin wrapper forwards the request to the Laravel /login route so
 * that both /login and the old /login.php URL render the same Blade view.
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parameters = $method === 'POST' ? $_POST : $_GET;

$request = Illuminate\Http\Request::create(
    $uri,
    $method,
    $parameters,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
