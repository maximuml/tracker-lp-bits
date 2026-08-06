<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
parked();
$rootpath = dirname(__DIR__) . '/';

if (! class_exists(\Illuminate\Http\Request::class)) {
    require_once $rootpath . 'vendor/autoload.php';
}

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = '/self-enable' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '/self-enable.php';
$server['SCRIPT_FILENAME'] = $rootpath . 'public/self-enable.php';
$server['REQUEST_METHOD'] = $method;

$request = \Illuminate\Http\Request::create(
    $uri,
    $method,
    $method === 'POST' ? $_POST : $_GET,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
