<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());

loggedinorreturn();

$rootpath = dirname(__DIR__) . '/';

if (! class_exists(\Illuminate\Http\Request::class)) {
    require_once $rootpath . 'vendor/autoload.php';
}

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

$id = (int) ($_GET['id'] ?? 0);

$query = $_GET;
unset($query['id']);

$uri = '/details/' . $id;
if ($query) {
    $uri .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

$server = $_SERVER;
$server['SCRIPT_NAME'] = '/details.php';
$server['SCRIPT_FILENAME'] = $rootpath . 'public/details.php';
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
