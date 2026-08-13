<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
define('IN_NEXUS', true);
define('NEXUS_START', microtime(true));
require ROOT_PATH . 'vendor/autoload.php';
require ROOT_PATH . 'config/nexus_constants.php';
$withLaravel = false;
if (file_exists(ROOT_PATH . '.env')) {
    $dbConfig = \App\Support\Config::get('nexus.database');
    $config = $dbConfig['connections'][$dbConfig['default']];
    \Nexus\Database\NexusDB::bootEloquent($config);
    $Cache = new \App\Support\Cache\LegacyRedisCache();
    $withLaravel = true;
}
define('WITH_LARAVEL', $withLaravel);
\Nexus\Nexus::boot();
$hook = new \Nexus\Plugin\Hook();
$plugin = new \Nexus\Plugin\Plugin();
