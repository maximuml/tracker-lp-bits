<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path('edit.php'));

loggedinorreturn();
parked();

$rootpath = dirname(__DIR__) . '/';

if (! class_exists(\Illuminate\Http\Request::class)) {
    require_once $rootpath . 'vendor/autoload.php';
}

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$query = $_GET;

$uri = '/upload' . (empty($query) ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

$server = $_SERVER;
$server['SCRIPT_NAME'] = '/upload.php';
$server['SCRIPT_FILENAME'] = $rootpath . 'public/upload.php';
$server['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$request = \Illuminate\Http\Request::create(
    $uri,
    $server['REQUEST_METHOD'],
    $query,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
