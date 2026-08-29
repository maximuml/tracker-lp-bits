<?php

declare(strict_types=1);

namespace Nexus;

use App\Http\Middleware\Locale;
use App\Support\Arrays;
use App\Support\Config;
use App\Support\Input;
use App\Support\Json;
use App\Support\Logger;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Queue\Capsule\Manager;
use Illuminate\Redis\RedisManager;
use Nexus\Translation\NexusTranslator;

final class Nexus
{
    private string $requestId;

    private int $logSequence = 0;

    private float $startTimestamp;

    private string $script;

    private string $platform;

    private static bool $booted = false;

    private static ?Nexus $instance = null;

    private static array $appendHeaders = [];

    private static array $appendFooters = [];

    private static array $translations = [];

    private static ?NexusTranslator $translator = null;

    private static ?Manager $queueManager = null;

    const QUEUE_CONNECTION_NAME = 'my_queue_connection';

    const PLATFORM_ADMIN = 'admin';

    private function __construct() {}

    private function __clone() {}

    public static function instance()
    {
        return self::$instance;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function getStartTimestamp(): float
    {
        return $this->startTimestamp;
    }

    public function getScript(): string
    {
        return $this->script;
    }

    public function getLogSequence(): int
    {
        return $this->logSequence;
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platform == self::PLATFORM_ADMIN;
    }

    public function incrementLogSequence(): void
    {
        $this->logSequence++;
    }

    private function getFirst(?string $result): string
    {
        if ($result === null || $result === '') {
            return '';
        }

        if (str_contains($result, ',')) {
            return strstr($result, ',', true);
        }

        return $result;
    }

    public function getRequestSchema(): string
    {
        $schema = $this->retrieveFromServer(['HTTP_X_FORWARDED_PROTO', 'REQUEST_SCHEME', 'HTTP_SCHEME']);
        if (empty($schema)) {
            $https = $this->retrieveFromServer(['HTTPS']);
            if ($https == 'on') {
                $schema = 'https';
            }
        }

        return $this->getFirst(is_string($schema) ? $schema : null);
    }

    public function getRequestHost(): string
    {
        $host = $this->retrieveFromServer(['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'host'], true);

        return $this->getFirst(strval($host));
    }

    private function retrieveFromServer(array $fields, bool $includeHeader = false): mixed
    {
        $request = $this->requestOrNull();

        foreach ($fields as $field) {
            if ($request !== null) {
                $result = $request->server($field);
                if ($result !== null && $result !== '') {
                    return $result;
                }

                if ($includeHeader) {
                    $result = $request->header($this->fieldToHeaderName($field));
                    if ($result !== null && $result !== '') {
                        return $result;
                    }
                }
            } else {
                $result = $_SERVER[$field] ?? null;
                if ($result !== null && $result !== '') {
                    return $result;
                }

                if ($includeHeader) {
                    $result = $this->headerFromServer($this->fieldToHeaderName($field));
                    if ($result !== null && $result !== '') {
                        return $result;
                    }
                }
            }
        }

        return null;
    }

    private function requestOrNull(): ?Request
    {
        $container = Container::getInstance();
        if (! $container->bound('request')) {
            return null;
        }

        /** @var Request $request */
        $request = $container->make('request');

        return $request;
    }

    private function fieldToHeaderName(string $field): string
    {
        if (str_starts_with($field, 'HTTP_')) {
            $field = substr($field, 5);
        }

        return str_replace('_', '-', strtolower($field));
    }

    private function headerFromServer(string $headerName): ?string
    {
        $serverName = 'HTTP_'.str_replace('-', '_', strtoupper($headerName));
        if (isset($_SERVER[$serverName])) {
            return $_SERVER[$serverName];
        }

        $serverName = str_replace('-', '_', strtoupper($headerName));
        if (in_array($serverName, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) && isset($_SERVER[$serverName])) {
            return $_SERVER[$serverName];
        }

        return null;
    }

    public function isAjax(): bool
    {
        if ($this->getScript() == 'ajax') {
            return true;
        }
        $ajax = $this->retrieveFromServer(['HTTP_X_REQUESTED_WITH'], true);
        if (! empty($ajax) && strtolower($ajax) == 'xmlhttprequest') {
            return true;
        }
        $json = $this->retrieveFromServer(['HTTP_ACCEPT'], true);
        if (! empty($json) && strtolower($json) == 'application/json') {
            return true;
        }

        return false;
    }

    private function generateRequestId(): string
    {
        $request = $this->requestOrNull();
        $scriptFilename = $request?->server('SCRIPT_FILENAME') ?? $_SERVER['SCRIPT_FILENAME'] ?? '';
        $argv = $request?->server('argv') ?? $_SERVER['argv'] ?? [];
        if (! is_array($argv)) {
            $argv = [$argv];
        }
        $prefix = $scriptFilename.implode('', $argv);
        $prefix = substr(md5($prefix), 0, 4);
        // 4 + 23 = 27 characters, after replace '.', 26
        $requestId = str_replace('.', '', uniqid($prefix, true));
        $requestId .= bin2hex(random_bytes(3));

        return $requestId;
    }

    public static function boot()
    {
        if (self::$booted) {
            //            file_put_contents('/tmp/reset.log', "booted\n",FILE_APPEND);
            return;
        }
        //        file_put_contents('/tmp/reset.log', "booting\n",FILE_APPEND);
        $instance = new self;
        $instance->setStartTimestamp();
        $instance->setRequestId();
        $instance->setScript();
        $instance->setPlatform();
        self::$instance = $instance;
        self::$booted = true;
    }

    public static function flush()
    {
        self::$booted = false;
        self::$instance = null;
        self::$appendHeaders = [];
        self::$appendFooters = [];
        self::$translator = null;
        self::$queueManager = null;
    }

    private function setRequestId()
    {
        $requestId = $this->retrieveFromServer(['HTTP_X_REQUEST_ID', 'REQUEST_ID', 'Request-Id', 'request-id'], true);
        if (empty($requestId)) {
            $requestId = $this->generateRequestId();
        }
        $this->requestId = (string) $requestId;
    }

    private function setScript()
    {
        $script = (string) $this->retrieveFromServer(['SCRIPT_FILENAME', 'SCRIPT_NAME', 'Script', 'script'], true);
        if (str_contains($script, '.')) {
            $script = strstr(basename($script), '.', true);
        }
        $this->script = (string) $script;
    }

    private function setStartTimestamp()
    {
        $this->startTimestamp = microtime(true);
    }

    private function setPlatform()
    {
        $this->platform = (string) $this->retrieveFromServer(['HTTP_PLATFORM', 'Platform', 'platform'], true);
    }

    public static function js(string $js, string $position, bool $isFile, $key = null)
    {
        if ($isFile) {
            $append = sprintf('<script type="text/javascript" src="%s"></script>', $js);
        } else {
            $append = sprintf('<script type="text/javascript">%s</script>', $js);
        }
        self::appendJsCss($append, $position, $key);
    }

    public static function css(string $css, string $position, bool $isFile, $key = null)
    {
        if ($isFile) {
            $append = sprintf('<link rel="stylesheet" href="%s" type="text/css">', $css);
        } else {
            $append = sprintf('<style type="text/css">%s</style>', $css);
        }
        self::appendJsCss($append, $position, $key);
    }

    private static function appendJsCss($append, $position, $key = null)
    {
        $log = "position: $position, key: $key";
        if ($key === null) {
            $key = md5($append);
            $log .= ", md5 key: $key";
        }
        if ($position == 'header') {
            if (! isset(self::$appendHeaders[$key])) {
                self::$appendHeaders[$key] = $append;
            } else {
                Logger::writeWithContext((string) "{$log}, [DUPLICATE]", (string) 'info', (bool) false);
            }
        } elseif ($position == 'footer') {
            if (! isset(self::$appendFooters[$key])) {
                self::$appendFooters[$key] = $append;
            } else {
                Logger::writeWithContext((string) "{$log}, [DUPLICATE]", (string) 'info', (bool) false);
            }
        } else {
            throw new \InvalidArgumentException("Invalid position: $position");
        }
    }

    public static function getAppendHeaders(): array
    {
        return self::$appendHeaders;
    }

    public static function getAppendFooters(): array
    {
        return self::$appendFooters;
    }

    public static function trans($key, $replace = [], $locale = null)
    {
        if ($locale === null) {
            $locale = \App\Support\Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), (bool) true);
        }
        if (IN_NEXUS) {
            return self::getTranslator()->trans($key, $replace, $locale);
        } else {
            return trans($key, $replace, $locale);
        }
        //        if (empty(self::$translations)) {
        //            //load from default lang dir
        //            $langDir = ROOT_PATH . 'resources/lang/';
        //            self::loadTranslations($langDir);
        //            //load from namespace
        //            foreach (self::$translationNamespaces as $namespace => $path) {
        //                self::loadTranslations($path, $namespace);
        //            }
        //        }
        //        return self::getTranslation($key, $replace, $locale ?? get_langfolder_cookie(true));
    }

    private static function loadTranslations($path, $namespace = null)
    {
        Logger::writeWithContext((string) "path: {$path}, namespace: {$namespace}", (string) 'debug', (bool) false);
        $files = glob($path.'*/*');
        foreach ($files as $file) {
            if (! is_file($file)) {
                Logger::writeWithContext((string) "file: {$file}, is not file", (string) 'debug', (bool) false);

                continue;
            }
            if (! is_readable($file)) {
                Logger::writeWithContext((string) "[TRANSLATION_FILE_NOT_READABLE], {$file}", (string) 'info', (bool) false);
            }
            $values = require $file;
            $setKey = substr($file, strlen($path));
            if (substr($setKey, -4) == '.php') {
                $setKey = substr($setKey, 0, -4);
            }
            $setKey = str_replace('/', '.', $setKey);
            if ($namespace !== null) {
                $setKey = "$namespace.$setKey";
            }
            Logger::writeWithContext((string) "path: {$path}, namespace: {$namespace}, file: {$file}, setKey: {$setKey}", (string) 'debug', (bool) false);
            Arrays::set(self::$translations, $setKey, $values);
        }
    }

    private static function getTranslation($key, $replace = [], $locale = null)
    {
        if (! $locale) {
            $lang = \App\Support\Locale::folderFromCookie(Input::cookieValue('c_lang_folder', ''), (bool) false);
            $locale = Locale::$languageMaps[$lang] ?? 'en';
        }
        $getKey = self::getTranslationGetKey($key, $locale);
        $result = Arrays::get(self::$translations, $getKey, null);
        if (empty($result) && $locale != 'en') {
            Logger::writeWithContext((string) "original getKey: {$getKey} can not get any translations", (string) 'error', (bool) false);
            $getKey = self::getTranslationGetKey($key, 'en');
            $result = Arrays::get(self::$translations, $getKey, null);
        }
        if (! empty($replace)) {
            $search = array_map(fn ($value) => ":$value", array_keys($replace));
            $result = str_replace($search, array_values($replace), $result);
        }
        Logger::writeWithContext((string) ("key: {$key}, replace: ".Json::encode($replace).", locale: {$locale}, getKey: {$getKey}, result: {$result}"), (string) 'debug', (bool) false);

        return $result;
    }

    private static function getTranslationGetKey($key, $locale): string
    {
        $namespace = strstr($key, '::', true);
        if ($namespace !== false) {
            $getKey = sprintf('%s.%s.%s', $namespace, $locale, substr($key, strlen($namespace) + 2));
        } else {
            $getKey = $locale.'.'.$key;
        }

        //        do_log("key: $key, locale: $locale, namespace: $namespace, getKey: $getKey", 'debug');
        return $getKey;
    }

    private static function getTranslator(): NexusTranslator
    {
        if (self::$translator === null) {
            self::$translator = new NexusTranslator(Locale::getDefault());
        }

        return self::$translator;
    }

    private static function getQueueManager(): Manager
    {
        if (self::$queueManager === null) {
            $container = Container::getInstance();
            $redisConfig = Config::get('nexus.redis', null);
            $redisConnectionName = 'my_redis_connection';
            $container->singleton('redis', function ($app) use ($redisConfig, $redisConnectionName) {
                $redisDriver = 'phpredis';
                // 这里的配置应该匹配 redis.php 配置文件中的 default 连接
                $connectionConfig = [
                    'client' => $redisDriver,
                    $redisConnectionName => $redisConfig,
                    // Preserve the 'default' and 'cache' connections so
                    // Redis::connection() / Redis::connection('cache') keep
                    // working after the queue manager rebinds the singleton.
                    'default' => $redisConfig,
                    'cache' => $redisConfig,
                ];

                return new RedisManager($app, $redisDriver, $connectionConfig);
            });
            $queueManager = new Manager($container);
            $queueManager->addConnection([
                'driver' => 'redis',
                'host' => $redisConfig['host'],
                'password' => $redisConfig['password'],
                'queue' => 'nexus_queue', // 队列名称
                'connection' => $redisConnectionName, // Redis 连接名称，类似注册的 'redis' 服务中的 'default'
            ], self::QUEUE_CONNECTION_NAME); // 将这个 queue 连接起个不一样的名字
            $queueManager->setAsGlobal();
            self::$queueManager = $queueManager;
        }

        return self::$queueManager;
    }

    public static function dispatchQueueJob(ShouldQueue $job): void
    {
        self::getQueueManager()->connection(self::QUEUE_CONNECTION_NAME)->push($job);
        Logger::writeWithContext((string) ('dispatchQueueJob: '.Json::encode($job)), (string) 'info', (bool) false);
    }
}
