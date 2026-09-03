<?php

use App\Logging\JsonLogFormatter;
use App\Logging\NexusFormatter;
use App\Logging\SensitiveDataRedactor;
use App\Support\Logger;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => array_filter(array_map('trim', explode(',', env('LOG_STACK', 'single')))),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'tap' => [SensitiveDataRedactor::class, NexusFormatter::class],
            'path' => Logger::filePath(''),
            'level' => env('LOG_LEVEL', 'debug'),
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => Logger::filePath(''),
            'level' => env('LOG_LEVEL', 'debug'),
            'tap' => [SensitiveDataRedactor::class, NexusFormatter::class],
            'days' => (int) env('LOG_DAILY_DAYS', 14),
            'ignore_exceptions' => false,
        ],

        'json' => [
            'driver' => 'daily',
            'path' => Logger::filePath('json'),
            'level' => env('LOG_LEVEL', 'debug'),
            'tap' => [SensitiveDataRedactor::class, JsonLogFormatter::class],
            'days' => (int) env('LOG_DAILY_DAYS', 14),
            'ignore_exceptions' => false,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_SLACK_LEVEL', 'critical'),
            'replace_nulls' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'deprecations' => [
            'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'ignore_exceptions' => false,
            'tap' => [SensitiveDataRedactor::class, NexusFormatter::class],
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => Logger::filePath(''),
        ],
    ],

];
