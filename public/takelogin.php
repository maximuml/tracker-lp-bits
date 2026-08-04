<?php

/**
 * Legacy login form handler.
 *
 * Forwards both GET and POST requests to the Laravel /login route so
 * the old URL keeps working while the new Blade form posts directly to
 * /login (or here) with a CSRF token.
 */

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$params = $method === 'POST' ? $_POST : $_GET;

$uri = '/login' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);

$request = Illuminate\Http\Request::create(
    $uri,
    $method,
    $params,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
