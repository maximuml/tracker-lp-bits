<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Metrics Access Token
    |--------------------------------------------------------------------------
    |
    | Bearer token for /metrics endpoint access. When empty, access is
    | restricted to private/internal networks only. Set METRICS_TOKEN
    | in production to allow external scraping with a bearer token.
    |
    */
    'token' => env('METRICS_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Dependency Check Cache TTL
    |--------------------------------------------------------------------------
    |
    | Seconds to cache dependency health check results (DB, Redis, Meili).
    | Prevents excessive load from frequent scraping.
    |
    */
    'dependency_cache_ttl' => 15,

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    |
    | Queue names to report depth for in /metrics.
    |
    */
    'queues' => [
        'tracker-critical',
        'default',
        'nexus_queue',
        'mail',
        'search',
        'maintenance',
    ],

];
