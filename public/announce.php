<?php

defined('IN_NEXUS') || define('IN_NEXUS', true);
defined('IN_TRACKER') || define('IN_TRACKER', true);

$rootpath = dirname(__DIR__) . '/';

require_once $rootpath . 'vendor/autoload.php';
$app = require_once $rootpath . 'bootstrap/app.php';

require_once $rootpath . 'include/core.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();

$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
