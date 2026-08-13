<?php

return [

    'timezone' => \App\Support\Env::get('TIMEZONE', 'PRC'),

    'log_file' => \App\Support\Env::get('LOG_FILE', '/tmp/nexus.log'),

    'log_split' => \App\Support\Env::get('LOG_SPLIT', 'daily'),

    'database' => [
        'default' => \App\Support\Env::get('DB_CONNECTION', 'mysql'),
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'url' => \App\Support\Env::get('DATABASE_URL', null),
                'host' => \App\Support\Env::get('DB_HOST', '127.0.0.1'),
                'port' => (int)\App\Support\Env::get('DB_PORT', 3306),
                'username' => \App\Support\Env::get('DB_USERNAME', 'root'),
                'password' => \App\Support\Env::get('DB_PASSWORD', ''),
                'database' => \App\Support\Env::get('DB_DATABASE', 'nexusphp'),
                'unix_socket' => \App\Support\Env::get('DB_SOCKET', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => false,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    PDO::MYSQL_ATTR_SSL_CA => \App\Support\Env::get('MYSQL_ATTR_SSL_CA', null),
                ]) : [],
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'url' => \App\Support\Env::get('DATABASE_URL', null),
                'host' => \App\Support\Env::get('DB_HOST', '127.0.0.1'),
                'port' => \App\Support\Env::get('DB_PORT', '5432'),
                'database' => \App\Support\Env::get('DB_DATABASE', 'nexusphp'),
                'username' => \App\Support\Env::get('DB_USERNAME', 'nexusphp'),
                'password' => \App\Support\Env::get('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'schema' => \App\Support\Env::get('DB_SCHEMA', 'public'),
                'sslmode' => 'prefer',
            ],
        ],
    ],

    'mysql' => [
        'driver' => 'mysql',
        'url' => \App\Support\Env::get('DATABASE_URL', null),
        'host' => \App\Support\Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int)\App\Support\Env::get('DB_PORT', 3306),
        'username' => \App\Support\Env::get('DB_USERNAME', 'root'),
        'password' => \App\Support\Env::get('DB_PASSWORD', ''),
        'database' => \App\Support\Env::get('DB_DATABASE', 'nexusphp'),
        'unix_socket' => \App\Support\Env::get('DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => false,
        'engine' => null,
        'options' => extension_loaded('pdo_mysql') ? array_filter([
            PDO::MYSQL_ATTR_SSL_CA => \App\Support\Env::get('MYSQL_ATTR_SSL_CA', null),
        ]) : [],
    ],

    'redis' => [
        'host' => \App\Support\Env::get('REDIS_HOST', '127.0.0.1'),
        'port' => (int)\App\Support\Env::get('REDIS_PORT', 6379),
        'database' => \App\Support\Env::get('REDIS_DB', 0),
        'password' => \App\Support\Env::get('REDIS_PASSWORD', null),
    ],

    'elasticsearch' => [
        'hosts' => [
            [
                'host' => \App\Support\Env::get('ELASTICSEARCH_HOST', 'localhost'),
                'port' => (int)\App\Support\Env::get('ELASTICSEARCH_PORT', '9200'),
                'scheme' => \App\Support\Env::get('ELASTICSEARCH_SCHEME', 'https'),
                'user' => \App\Support\Env::get('ELASTICSEARCH_USER', 'elastic'),
                'pass' => \App\Support\Env::get('ELASTICSEARCH_PASS', ''),
            ]
        ],

        'ssl_verification' => \App\Support\Env::get('ELASTICSEARCH_SSL_VERIFICATION', ''),
    ],

    'meilisearch' => [
        'scheme' => \App\Support\Env::get('MEILISEARCH_SCHEME', 'http'),
        'host' => \App\Support\Env::get('MEILISEARCH_HOST', 'meilisearch'),
        'port' => (int)\App\Support\Env::get('MEILISEARCH_PORT', '7700'),
        'master_key' => \App\Support\Env::get('MEILISEARCH_MASTER_KEY', ''),
    ],

    'nas_tools_key' => \App\Support\Env::get('NAS_TOOLS_KEY', ''),
    'iyuu_secret' => \App\Support\Env::get('IYUU_SECRET', ''),
    'ammds_secret' => \App\Support\Env::get('AMMDS_SECRET', ''),

    'trusted_proxies' => \App\Support\Env::get('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1'),


];
