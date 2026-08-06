<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    /** @var array<string, mixed> */
    private static array $userUpdateSet = [];

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
        self::$userUpdateSet = (array) ($GLOBALS['USERUPDATESET'] ?? []);
        $GLOBALS['USERUPDATESET'] = &self::$userUpdateSet;
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
        self::$userUpdateSet = (array) ($GLOBALS['USERUPDATESET'] ?? []);
        $GLOBALS['USERUPDATESET'] = &self::$userUpdateSet;
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
        self::setGlobal('CURUSER', $user);
    }

    /**
     * Return a reference to the legacy per-request user update set.
     *
     * `stdhead()`/`stdfoot()` flush this array to the `users` table, so
     * callers must mutate the same array between header and footer.
     *
     * @return  array<string, mixed>
     */
    public static function &getUserUpdateSet(): array
    {
        return self::$userUpdateSet;
    }

    /** @param  array<string, mixed>  $data */
    public static function setUserUpdateSet(array $data): void
    {
        self::$userUpdateSet = $data;
    }

    public static function addUserUpdate(string $key, mixed $value): void
    {
        self::$userUpdateSet[$key] = $value;
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getUser(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (! empty($GLOBALS['CURUSER'])) {
            return $GLOBALS['CURUSER'];
        }

        $app = function_exists('app') ? app() : null;
        if (is_object($app) && method_exists($app, 'isBooted') && $app->isBooted()) {
            $authUser = Auth::guard('nexus-web')->user();
            if ($authUser !== null) {
                return $authUser->toArray();
            }
        }

        return null;
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

        $globals = [
            'SITENAME' => (string) ($GLOBALS['SITENAME'] ?? ''),
            'SITEEMAIL' => (string) ($GLOBALS['SITEEMAIL'] ?? ''),
            'smtptype' => (string) ($GLOBALS['smtptype'] ?? ''),
            'smtp' => (string) ($GLOBALS['smtp'] ?? ''),
            'smtp_host' => (string) ($GLOBALS['smtp_host'] ?? ''),
            'smtp_port' => (string) ($GLOBALS['smtp_port'] ?? ''),
            'smtp_from' => (string) ($GLOBALS['smtp_from'] ?? ''),
        ];

        $keys = [
            'SITENAME' => 'basic.SITENAME',
            'SITEEMAIL' => 'main.SITEEMAIL',
            'smtptype' => 'smtp.smtptype',
            'smtp' => 'smtp.smtp',
            'smtp_host' => 'smtp.smtp_host',
            'smtp_port' => 'smtp.smtp_port',
            'smtp_from' => 'smtp.smtp_from',
        ];

        if (class_exists(\App\Models\Setting::class)) {
            foreach ($keys as $name => $settingName) {
                $value = \App\Models\Setting::get($settingName);
                if (! is_null($value)) {
                    $globals[$name] = (string) $value;
                }
            }
        }

        return $globals;
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

    /**
     * Return a snapshot of the legacy global state suitable for passing to
     * Blade/PHP partials. Superglobals and internal Laravel/bootstrap variables
     * are excluded so views do not re-import PHP internals as local variables.
     *
     * @return array<string, mixed>
     */
    public static function getGlobalsForView(): array
    {
        $excluded = [
            'GLOBALS',
            '_GET',
            '_POST',
            '_REQUEST',
            '_SERVER',
            '_FILES',
            '_COOKIE',
            '_ENV',
            'argc',
            'argv',
            'app',
            'kernel',
            'request',
            'response',
            'parameters',
            'files',
            'server',
            'method',
            'uri',
            'routePath',
            'pathInfo',
            'queryString',
            'isWrapper',
            'page',
            'executedScript',
            'scriptFilename',
            'scriptName',
            'parsedUrl',
            'requestPath',
            'requestUri',
            'nexusRoute',
            'parkedScripts',
            'extraLangFiles',
            'scriptLangFiles',
            'scriptLangFile',
            'langPath',
            'HTTP_RAW_POST_DATA',
            '__composer_autoload_files',
        ];

        $context = [];
        foreach ($GLOBALS as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }
            $context[$key] = $value;
        }

        return $context;
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

        $request = self::getLaravelRequest();
        if ($request !== null) {
            $value = $request->server->get($key);
            if ($value !== null) {
                return $value;
            }
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
        if (array_key_exists($key, self::$cookie)) {
            $value = self::$cookie[$key];
        } else {
            $request = self::getLaravelRequest();
            if ($request !== null) {
                $value = $request->cookies->get($key);
            } else {
                $value = $_COOKIE[$key] ?? $default;
            }
        }

        if (! isset($value)) {
            $value = $default;
        }

        return is_string($value) || $value === null ? $value : (string) $value;
    }

    /** @return  array<string, mixed> */
    public static function allCookie(): array
    {
        if (! empty(self::$cookie)) {
            return self::$cookie;
        }

        $request = self::getLaravelRequest();
        return $request !== null ? $request->cookies->all() : $_COOKIE;
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
        if (array_key_exists($key, self::$get)) {
            return self::$get[$key];
        }

        $request = self::getLaravelRequest();
        if ($request !== null) {
            return $request->query($key, $default);
        }

        return $_GET[$key] ?? $default;
    }

    /** @return  array<string, mixed> */
    public static function allQuery(): array
    {
        if (! empty(self::$get)) {
            return self::$get;
        }

        $request = self::getLaravelRequest();
        return $request !== null ? $request->query->all() : $_GET;
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
        if (array_key_exists($key, self::$post)) {
            return self::$post[$key];
        }

        $request = self::getLaravelRequest();
        if ($request !== null) {
            return $request->request->all()[$key] ?? $default;
        }

        return $_POST[$key] ?? $default;
    }

    /** @return  array<string, mixed> */
    public static function allPost(): array
    {
        if (! empty(self::$post)) {
            return self::$post;
        }

        $request = self::getLaravelRequest();
        return $request !== null ? $request->request->all() : $_POST;
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
        if (array_key_exists($key, self::$request)) {
            return self::$request[$key];
        }

        $request = self::getLaravelRequest();
        if ($request !== null) {
            return $request->input($key, $default);
        }

        return $_REQUEST[$key] ?? $default;
    }

    /** @return  array<string, mixed> */
    public static function allRequest(): array
    {
        if (! empty(self::$request)) {
            return self::$request;
        }

        $request = self::getLaravelRequest();
        return $request !== null ? $request->input() : $_REQUEST;
    }

    public static function setLaravelRequest(?Request $request): void
    {
        self::$laravelRequest = $request;
    }

    public static function getLaravelRequest(): ?Request
    {
        if (self::$laravelRequest !== null) {
            return self::$laravelRequest;
        }

        if (function_exists('app')) {
            $app = app();
            if ($app->bound('request')) {
                $request = $app->make('request');
                if ($request instanceof Request) {
                    return $request;
                }
            }
        }

        return null;
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
        self::$userUpdateSet = [];
        self::$laravelRequest = null;
    }
}
