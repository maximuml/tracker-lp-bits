<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

final class RequestContext
{
    private string $requestId;

    private int $logSequence = 0;

    private float $startTimestamp;

    private string $script;

    private string $platform;

    private static bool $booted = false;

    private static ?RequestContext $instance = null;

    const PLATFORM_ADMIN = 'admin';

    private function __construct() {}

    private function __clone() {}

    public static function instance(): self
    {
        if (! self::$booted) {
            self::boot();
        }

        /** @var self $instance */
        $instance = self::$instance;

        return $instance;
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
            return strstr($result, ',', true) ?: '';
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

    /** @param  array<int, string>  $fields */
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

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        $instance = new self;
        $instance->setStartTimestamp();
        $instance->setRequestId();
        $instance->setScript();
        $instance->setPlatform();
        self::$instance = $instance;
        self::$booted = true;
    }

    public static function flush(): void
    {
        self::$booted = false;
        self::$instance = null;
    }

    private function setRequestId(): void
    {
        $requestId = $this->retrieveFromServer(['HTTP_X_REQUEST_ID', 'REQUEST_ID', 'Request-Id', 'request-id'], true);
        if (empty($requestId)) {
            $requestId = $this->generateRequestId();
        }
        $this->requestId = (string) $requestId;
    }

    private function setScript(): void
    {
        $script = (string) $this->retrieveFromServer(['SCRIPT_FILENAME', 'SCRIPT_NAME', 'Script', 'script'], true);
        if (str_contains($script, '.')) {
            $script = strstr(basename($script), '.', true);
        }
        $this->script = (string) $script;
    }

    private function setStartTimestamp(): void
    {
        $this->startTimestamp = microtime(true);
    }

    private function setPlatform(): void
    {
        $this->platform = (string) $this->retrieveFromServer(['HTTP_PLATFORM', 'Platform', 'platform'], true);
    }
}
