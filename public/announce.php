<?php

defined('IN_NEXUS') || define('IN_NEXUS', true);
defined('IN_TRACKER') || define('IN_TRACKER', true);

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

require_once $rootpath . 'include/core.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// When announce.php is executed directly by the FPM worker, Symfony's request
// parser treats the script name as the base URL and derives an empty path info,
// which makes Laravel's router return 404. Build the request manually so the
// path info is /announce while preserving the original query parameters,
// headers and client IP.
$server = $_SERVER;
$server['SCRIPT_NAME'] = '';
$server['SCRIPT_FILENAME'] = '';
$server['PHP_SELF'] = '';

$uri = '/announce' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);
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
