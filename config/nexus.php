<?php

use App\Support\Env;

return [

    'timezone' => Env::get('TIMEZONE', 'PRC'),

    'log_file' => Env::get('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => Env::get('LOG_SPLIT', 'daily'),

    'database' => [
        'default' => Env::get('DB_CONNECTION', 'mysql'),
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'url' => Env::get('DATABASE_URL', null),
                'host' => Env::get('DB_HOST', '127.0.0.1'),
                'port' => (int) Env::get('DB_PORT', 3306),
                'username' => Env::get('DB_USERNAME', 'root'),
                'password' => Env::get('DB_PASSWORD', ''),
                'database' => Env::get('DB_DATABASE', 'nexusphp'),
                'unix_socket' => Env::get('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => false,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    1009, // PDO::MYSQL_ATTR_SSL_CA (deprecated in PHP 8.5) => Env::get('MYSQL_ATTR_SSL_CA', null),
                ]) : [],
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'url' => Env::get('DATABASE_URL', null),
                'host' => Env::get('DB_HOST', '127.0.0.1'),
                'port' => Env::get('DB_PORT', '5432'),
                'database' => Env::get('DB_DATABASE', 'nexusphp'),
                'username' => Env::get('DB_USERNAME', 'nexusphp'),
                'password' => Env::get('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'schema' => Env::get('DB_SCHEMA', 'public'),
                'sslmode' => 'prefer',
            ],
        ],
    ],

    'mysql' => [
        'driver' => 'mysql',
        'url' => Env::get('DATABASE_URL', null),
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', 3306),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'database' => Env::get('DB_DATABASE', 'nexusphp'),
        'unix_socket' => Env::get('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            1009, // PDO::MYSQL_ATTR_SSL_CA (deprecated in PHP 8.5) => Env::get('MYSQL_ATTR_SSL_CA', null),
        ]) : [],
    ],

    'redis' => [
        'host' => Env::get('REDIS_HOST', '127.0.0.1'),
        'port' => (int) Env::get('REDIS_PORT', 6379),
        'database' => Env::get('REDIS_DB', 0),
        'password' => Env::get('REDIS_PASSWORD', null),
    ],

    'meilisearch' => [
        'scheme' => Env::get('MEILISEARCH_SCHEME', 'http'),
        'host' => Env::get('MEILISEARCH_HOST', 'meilisearch'),
        'port' => (int) Env::get('MEILISEARCH_PORT', '7700'),
        'master_key' => Env::get('MEILISEARCH_MASTER_KEY', ''),
    ],

    'nas_tools_key' => Env::get('NAS_TOOLS_KEY', ''),
    'iyuu_secret' => Env::get('IYUU_SECRET', ''),
    'ammds_secret' => Env::get('AMMDS_SECRET', ''),

    'trusted_proxies' => Env::get('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1'),

];
