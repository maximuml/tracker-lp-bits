<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Centralised, static runtime context for legacy helpers.
 *
 * This is an intermediate migration step: it lets `App\Support` classes stop
 * reading `$GLOBALS` / `$_GET` / `$_POST` / `$_SERVER` / `$_COOKIE` directly
 * while legacy pages still work. Legacy entrypoints can populate the context
 * explicitly via `fromGlobals()`; Laravel entrypoints can use `fromRequest()`
 * or individual setters. When a value has not been set, getters fall back to
 * the corresponding PHP superglobal so legacy callers continue to work.
 */
final class SupportContext
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @var array<string, string> */
    private static array $langFunctions = [];

    /** @var array<string, string> */
    private static array $langShoutbox = [];

    /** @var object|null */
    private static ?object $cache = null;

    private static string $bonusTweak = '';

    /** @var array<string, mixed> */
    private static array $siteConfig = [];

    /** @var array<string, mixed> */
    private static array $server = [];

    /** @var array<string, mixed> */
    private static array $cookie = [];

    /** @var array<string, mixed> */
    private static array $get = [];

    /** @var array<string, mixed> */
    private static array $post = [];

    /** @var array<string, mixed> */
    private static array $request = [];

    private static ?Request $laravelRequest = null;

    /**
     * Populate the context from the current PHP superglobals.
     *
     * Legacy pages call this once after `$CURUSER`, `$lang_functions`, etc.
     * have been initialised.
     */
    public static function fromGlobals(): void
    {
        self::$user = $GLOBALS['CURUSER'] ?? null;
        self::$langFunctions = (array) ($GLOBALS['lang_functions'] ?? []);
        self::$langShoutbox = (array) ($GLOBALS['lang_shoutbox'] ?? []);
        self::$cache = $GLOBALS['Cache'] ?? null;
        self::$bonusTweak = (string) ($GLOBALS['bonus_tweak'] ?? '');
        self::$siteConfig = [
            'SITENAME' => (string) ($GLOBALS['SITENAME'] ?? ''),
            'SITEEMAIL' => (string) ($GLOBALS['SITEEMAIL'] ?? ''),
            'smtptype' => (string) ($GLOBALS['smtptype'] ?? ''),
            'smtp' => (string) ($GLOBALS['smtp'] ?? ''),
            'smtp_host' => (string) ($GLOBALS['smtp_host'] ?? ''),
            'smtp_port' => (string) ($GLOBALS['smtp_port'] ?? ''),
            'smtp_from' => (string) ($GLOBALS['smtp_from'] ?? ''),
        ];
        self::$server = $_SERVER;
        self::$cookie = $_COOKIE;
        self::$get = $_GET;
        self::$post = $_POST;
        self::$request = $_REQUEST;
    }

    /**
     * Populate the context from a Laravel request and the application settings.
     */
    public static function fromRequest(Request $request): void
    {
        self::$laravelRequest = $request;
        self::$server = $request->server->all();
        self::$cookie = $request->cookies->all();
        self::$get = $request->query->all();
        self::$post = $request->request->all();
        self::$request = $request->input();
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getUser(): ?array
    {
        return self::$user ?? ($GLOBALS['CURUSER'] ?? null);
    }

    /** @param  array<string, string>  $lang */
    public static function setLangFunctions(array $lang): void
    {
        self::$langFunctions = $lang;
    }

    /** @return  array<string, string> */
    public static function getLangFunctions(): array
    {
        return self::$langFunctions ?: (array) ($GLOBALS['lang_functions'] ?? []);
    }

    /** @param  array<string, string>  $lang */
    public static function setLangShoutbox(array $lang): void
    {
        self::$langShoutbox = $lang;
    }

    /** @return  array<string, string> */
    public static function getLangShoutbox(): array
    {
        return self::$langShoutbox ?: (array) ($GLOBALS['lang_shoutbox'] ?? []);
    }

    public static function setCache(?object $cache): void
    {
        self::$cache = $cache;
    }

    public static function getCache(): ?object
    {
        return self::$cache ?? ($GLOBALS['Cache'] ?? null);
    }

    public static function setBonusTweak(string $value): void
    {
        self::$bonusTweak = $value;
    }

    public static function getBonusTweak(): string
    {
        return self::$bonusTweak ?: (string) ($GLOBALS['bonus_tweak'] ?? '');
    }

    /** @param  array<string, mixed>  $config */
    public static function setSiteConfig(array $config): void
    {
        self::$siteConfig = $config;
    }

    /** @return  array<string, mixed> */
    public static function getSiteConfig(): array
    {
        if (! empty(self::$siteConfig)) {
            return self::$siteConfig;
        }

        return [
            'SITENAME' => (string) ($GLOBALS['SITENAME'] ?? ''),
            'SITEEMAIL' => (string) ($GLOBALS['SITEEMAIL'] ?? ''),
            'smtptype' => (string) ($GLOBALS['smtptype'] ?? ''),
            'smtp' => (string) ($GLOBALS['smtp'] ?? ''),
            'smtp_host' => (string) ($GLOBALS['smtp_host'] ?? ''),
            'smtp_port' => (string) ($GLOBALS['smtp_port'] ?? ''),
            'smtp_from' => (string) ($GLOBALS['smtp_from'] ?? ''),
        ];
    }

    public static function setGlobal(string $key, mixed $value): void
    {
        $GLOBALS[$key] = $value;
    }

    /** @return  mixed */
    public static function getGlobal(string $key, mixed $default = null): mixed
    {
        return $GLOBALS[$key] ?? $default;
    }

    public static function setServerValue(string $key, mixed $value): void
    {
        self::$server[$key] = $value;
    }

    public static function getServerValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$server)) {
            return self::$server[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /** @param  array<string, mixed>  $cookie */
    public static function setCookie(array $cookie): void
    {
        self::$cookie = $cookie;
    }

    public static function getCookieValue(string $key, ?string $default = null): ?string
    {
        $value = self::$cookie[$key] ?? $_COOKIE[$key] ?? $default;

        return is_string($value) || $value === null ? $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $get
     */
    public static function setGet(array $get): void
    {
        self::$get = $get;
    }

    public static function getQuery(string $key, mixed $default = null): mixed
    {
        return self::$get[$key] ?? $_GET[$key] ?? $default;
    }

    /** @return  array<string, mixed> */
    public static function allQuery(): array
    {
        return self::$get ?: $_GET;
    }

    /**
     * @param  array<string, mixed>  $post
     */
    public static function setPost(array $post): void
    {
        self::$post = $post;
    }

    public static function getPost(string $key, mixed $default = null): mixed
    {
        return self::$post[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $request
     */
    public static function setRequest(array $request): void
    {
        self::$request = $request;
    }

    public static function getRequestInput(string $key, mixed $default = null): mixed
    {
        return self::$request[$key] ?? $_REQUEST[$key] ?? $default;
    }

    public static function setLaravelRequest(?Request $request): void
    {
        self::$laravelRequest = $request;
    }

    public static function getLaravelRequest(): ?Request
    {
        return self::$laravelRequest ?? (function_exists('request') ? request() : null);
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$langFunctions = [];
        self::$langShoutbox = [];
        self::$cache = null;
        self::$bonusTweak = '';
        self::$siteConfig = [];
        self::$server = [];
        self::$cookie = [];
        self::$get = [];
        self::$post = [];
        self::$request = [];
        self::$laravelRequest = null;
    }
}
