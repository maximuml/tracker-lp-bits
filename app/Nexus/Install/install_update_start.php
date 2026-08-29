<?php

declare(strict_types=1);

use App\Support\Cache\LegacyRedisCache;
use App\Support\Config;
use App\Support\RequestContext;
use Nexus\Database\NexusDB;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
define('IN_NEXUS', true);
define('NEXUS_START', microtime(true));
require ROOT_PATH.'vendor/autoload.php';
require ROOT_PATH.'config/nexus_constants.php';
$withLaravel = false;
if (file_exists(ROOT_PATH.'.env')) {
    $dbConfig = Config::get('nexus.database');
    $config = $dbConfig['connections'][$dbConfig['default']];
    NexusDB::bootEloquent($config);
    $Cache = new LegacyRedisCache;
    $withLaravel = true;
}
define('WITH_LARAVEL', $withLaravel);
RequestContext::boot();
