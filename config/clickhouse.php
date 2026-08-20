<?php

/*
 * This file is part of Laravel ClickHouse.
 *
 * (c) Anton Komarev <anton@komarev.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);
use App\Support\Env;

return [

    /*
    |--------------------------------------------------------------------------
    | ClickHouse Client Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure a connection to connect to the ClickHouse
    | database and specify additional configuration options.
    |
    */

    'connection' => [
        'host' => Env::get('CLICKHOUSE_HOST', 'localhost'),
        'port' => Env::get('CLICKHOUSE_HTTP_PORT', 8123),
        'username' => Env::get('CLICKHOUSE_USER', 'default'),
        'password' => Env::get('CLICKHOUSE_PASSWORD', ''),
        'options' => [
            'database' => Env::get('CLICKHOUSE_DATABASE', 'default'),
            'timeout' => 1,
            'connectTimeOut' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ClickHouse Migration Settings
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'table' => Env::get('CLICKHOUSE_MIGRATION_TABLE', 'migrations'),
        'path' => __DIR__.'/../database/clickhouse-migrations',
    ],
];
