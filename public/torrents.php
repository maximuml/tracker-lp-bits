<?php
require_once("../include/bittorrent.php");
dbconn(true);
require_once(get_langfile_path('torrents.php'));
require_once(get_langfile_path('special.php'));
loggedinorreturn();
parked();

$rootpath = dirname(__DIR__) . '/';

if (! class_exists(\Illuminate\Http\Request::class)) {
    require_once $rootpath . 'vendor/autoload.php';
}

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$query = $_GET;
$uri = '/torrents';
if ($query) {
    $uri .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

$server = $_SERVER;
$server['SCRIPT_NAME'] = '/torrents.php';
$server['SCRIPT_FILENAME'] = $rootpath . 'public/torrents.php';
$server['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$request = \Illuminate\Http\Request::create(
    $uri,
    'GET',
    $query,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
