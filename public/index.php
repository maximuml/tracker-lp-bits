<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
defined('IN_NEXUS') || define('IN_NEXUS', true);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('#^/(?:announce|scrape)(?:\.php)?(?:/|$|\?)#', $requestUri)) {
    defined('IN_TRACKER') || define('IN_TRACKER', true);
}

$rootpath = dirname(__DIR__).'/';
set_include_path(get_include_path().PATH_SEPARATOR.$rootpath);

if (file_exists(__DIR__.'/../storage/framework/maintenance.php')) {
    require __DIR__.'/../storage/framework/maintenance.php';
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$request = Request::capture();

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
