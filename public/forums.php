<?php
$rootpath = dirname(__DIR__) . '/';

// Ensure the legacy locale helpers pick the right language file.
$_SERVER['SCRIPT_NAME'] = '/forums.php';
$_SERVER['SCRIPT_FILENAME'] = $rootpath . 'public/forums.php';

require_once $rootpath . 'include/bittorrent.php';
dbconn();
require_once(get_langfile_path('forums.php'));
loggedinorreturn();
parked();

$app = require_once $rootpath . 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$queryString = $_SERVER['QUERY_STRING'] ?? '';
$uri = '/forums' . ($queryString !== '' ? '?' . $queryString : '');

$parameters = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? $_GET : $_POST;

$request = Illuminate\Http\Request::create(
    $uri,
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $parameters,
    $_COOKIE,
    $_FILES,
    $server
);

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
