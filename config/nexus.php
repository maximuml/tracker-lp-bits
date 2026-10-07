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

    'meilisearch' => [
        'scheme' => Env::get('MEILISEARCH_SCHEME', 'http'),
        'host' => Env::get('MEILISEARCH_HOST', 'meilisearch'),
        'port' => (int) Env::get('MEILISEARCH_PORT', '7700'),
        'master_key' => Env::get('MEILISEARCH_MASTER_KEY', ''),
    ],

    'trusted_proxies' => Env::get('TRUSTED_PROXIES', ''),

    // NEXUS_RATE_LIMITING=false disables every `throttle:` middleware check
    // (named limiters and inline throttle:N,M alike). E2E/browser stacks set
    // this: the whole suite shares one IP, so per-IP buckets exhaust within a
    // minute and flake unrelated tests with 429s. Tracker announce throttling
    // (throttle.tracker) is a separate middleware and is unaffected.
    'rate_limiting' => Env::get('NEXUS_RATE_LIMITING', true),

    // false makes public/cron.php refuse to run cleanup (legacy
    // browser-triggered mode).
    'cleanup_cron_triggered' => true,

];
