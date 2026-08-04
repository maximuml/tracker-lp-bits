<?php
$rootpath = dirname(__DIR__) . '/';

// Ensure the legacy locale helpers pick the right language file.
$_SERVER['SCRIPT_NAME'] = '/userdetails.php';
$_SERVER['SCRIPT_FILENAME'] = $rootpath . 'public/userdetails.php';

require_once $rootpath . 'include/bittorrent.php';
dbconn();
require_once(get_langfile_path('userdetails.php'));
loggedinorreturn();
parked();

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$uri = '/userdetails' . ($queryString !== '' ? '?' . $queryString : '');

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
