<?php

namespace App\Support;

use App\Support\Config\SiteConfig;
use Illuminate\Http\Request;

/**
 * Per-request value object that holds all legacy runtime state.
 *
 * The context is populated from a Laravel Request and then read by the
 * SupportContext facade and legacy helpers. No PHP superglobals or $GLOBALS
 * are used after the initial Request capture.
 */
final class NexusContext
{
    public ?Request $laravelRequest = null;

    /** @var array<string, mixed>|null */
    public ?array $user = null;

    /** @var array<string, string> */
    public array $langFunctions = [];

    /** @var array<string, string> */
    public array $langShoutbox = [];

    public ?object $cache = null;

    public string $bonusTweak = '';

    /** @var array<string, mixed> */
    public array $siteConfig = [];

    /** @var array<string, mixed> */
    public array $server = [];

    /** @var array<string, mixed> */
    public array $cookie = [];

    /** @var array<string, mixed> */
    public array $get = [];

    /** @var array<string, mixed> */
    public array $post = [];

    /** @var array<string, mixed> */
    public array $request = [];

    /** @var array<string, mixed> */
    public array $files = [];

    /** @var array<string, mixed> */
    public array $userUpdateSet = [];

    /** @var array<string, mixed> */
    public array $globals = [];

    /**
     * Variables that are PHP internals/Laravel bootstraps and should never be
     * exposed to legacy views or copied from $GLOBALS.
     *
     * @var list<string>
     */
    private const GLOBALS_EXCLUDED = [
        'GLOBALS', '_GET', '_POST', '_REQUEST', '_SERVER', '_FILES', '_COOKIE', '_ENV',
        'argc', 'argv', 'app', 'kernel', 'request', 'response',
        'parameters', 'files', 'server', 'method', 'uri', 'routePath', 'pathInfo',
        'queryString', 'isWrapper', 'page', 'executedScript', 'scriptFilename', 'scriptName',
        'parsedUrl', 'requestPath', 'requestUri', 'nexusRoute', 'parkedScripts',
        'extraLangFiles', 'scriptLangFiles', 'scriptLangFile', 'langPath', 'HTTP_RAW_POST_DATA',
        '__composer_autoload_files',
    ];

    public static function fromRequest(Request $request): self
    {
        $context = new self();
        $context->setFromRequest($request);

        return $context;
    }

    public function setFromRequest(Request $request): void
    {
        $this->laravelRequest = $request;
        $this->server = $request->server->all();
        $this->cookie = $request->cookies->all();
        $this->get = $request->query->all();
        $this->post = $request->request->all();
        $this->request = $request->input();
        $this->files = $this->normalizeFiles($request->files->all());

        $this->ensureUserUpdateSetReference();
    }

    /**
     * Convert Symfony UploadedFile objects into the legacy array format so
     * legacy partials can keep using $file['tmp_name'], $file['size'], etc.
     *
     * @param  array<string, mixed>  $files
     * @return array<string, mixed>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            } elseif ($value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                $normalized[$key] = [
                    'name' => $value->getClientOriginalName(),
                    'type' => $value->getClientMimeType(),
                    'tmp_name' => $value->getPathname(),
                    'error' => $value->getError(),
                    'size' => $value->getSize(),
                ];
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, mixed>|null $user */
    public function setUser(?array $user): void
    {
        $this->user = $user;
        $this->globals['CURUSER'] = $user;
    }

    /** @return array<string, mixed>|null */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /** @return array<string, mixed> */
    public function &getUserUpdateSet(): array
    {
        return $this->userUpdateSet;
    }

    /** @param array<string, mixed> $data */
    public function setUserUpdateSet(array $data): void
    {
        $this->globals['USERUPDATESET'] = $data;
        $this->userUpdateSet = &$this->globals['USERUPDATESET'];
    }

    public function addUserUpdate(string $key, mixed $value): void
    {
        $this->userUpdateSet[$key] = $value;
    }

    /** @param array<string, string> $lang */
    public function setLangFunctions(array $lang): void
    {
        $this->langFunctions = $lang;
        $this->globals['lang_functions'] = $lang;
    }

    /** @return array<string, string> */
    public function getLangFunctions(): array
    {
        return $this->langFunctions;
    }

    /** @param array<string, string> $lang */
    public function setLangShoutbox(array $lang): void
    {
        $this->langShoutbox = $lang;
        $this->globals['lang_shoutbox'] = $lang;
    }

    /** @return array<string, string> */
    public function getLangShoutbox(): array
    {
        return $this->langShoutbox;
    }

    public function setCache(?object $cache): void
    {
        $this->cache = $cache;
        $this->globals['Cache'] = $cache;
    }

    public function getCache(): ?object
    {
        return $this->cache;
    }

    public function setBonusTweak(string $value): void
    {
        $this->bonusTweak = $value;
        $this->globals['bonus_tweak'] = $value;
    }

    public function getBonusTweak(): string
    {
        return $this->bonusTweak;
    }

    /** @param array<string, mixed> $config */
    public function setSiteConfig(array $config): void
    {
        $this->siteConfig = $config;
        foreach ($config as $key => $value) {
            $this->globals[$key] = $value;
        }
    }

    /** @return array<string, mixed> */
    public function getSiteConfig(): array
    {
        if (! empty($this->siteConfig)) {
            return $this->siteConfig;
        }

        $siteConfig = SiteConfig::current();

        return $this->siteConfig = [
            'SITENAME' => $siteConfig->basic->siteName(),
            'SITEEMAIL' => $siteConfig->main->siteEmail(),
            'smtptype' => $siteConfig->smtp->type(),
            'smtp' => $siteConfig->smtp->smtp(),
            'smtp_host' => $siteConfig->smtp->host(),
            'smtp_port' => $siteConfig->smtp->port(),
            'smtp_from' => $siteConfig->smtp->from(),
        ];
    }

    public function setGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    public function getGlobal(string $key, mixed $default = null): mixed
    {
        return $this->globals[$key] ?? $default;
    }

    /**
     * Return a snapshot of the legacy global state suitable for passing to
     * Blade/PHP partials.
     *
     * @return array<string, mixed>
     */
    public function getGlobalsForView(): array
    {
        $context = $this->globals;
        foreach (self::GLOBALS_EXCLUDED as $key) {
            unset($context[$key]);
        }

        return $context;
    }

    public function setServerValue(string $key, mixed $value): void
    {
        $this->server[$key] = $value;
    }

    public function getServerValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->server)) {
            return $this->server[$key];
        }

        if ($this->laravelRequest !== null) {
            $value = $this->laravelRequest->server->get($key);
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /** @param array<string, mixed> $cookie */
    public function setCookie(array $cookie): void
    {
        $this->cookie = $cookie;
    }

    public function getCookieValue(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $this->cookie)) {
            $value = $this->cookie[$key];
        } elseif ($this->laravelRequest !== null) {
            $value = $this->laravelRequest->cookies->get($key);
        } else {
            $value = $default;
        }

        if (! isset($value)) {
            $value = $default;
        }

        return is_string($value) || $value === null ? $value : (string) $value;
    }

    /** @return array<string, mixed> */
    public function allCookie(): array
    {
        if (! empty($this->cookie)) {
            return $this->cookie;
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->cookies->all();
        }

        return [];
    }

    /** @param array<string, mixed> $get */
    public function setGet(array $get): void
    {
        $this->get = $get;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->query($key, $default);
        }

        return $default;
    }

    public function removeQuery(string $key): void
    {
        unset($this->get[$key]);
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        if (! empty($this->get)) {
            return $this->get;
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->query->all();
        }

        return [];
    }

    /** @param array<string, mixed> $post */
    public function setPost(array $post): void
    {
        $this->post = $post;
    }

    public function getPost(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->request->all()[$key] ?? $default;
        }

        return $default;
    }

    public function removePost(string $key): void
    {
        unset($this->post[$key]);
    }

    /** @return array<string, mixed> */
    public function allPost(): array
    {
        if (! empty($this->post)) {
            return $this->post;
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->request->all();
        }

        return [];
    }

    /** @param array<string, mixed> $request */
    public function setRequest(array $request): void
    {
        $this->request = $request;
    }

    public function getRequestInput(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->request)) {
            return $this->request[$key];
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->input($key, $default);
        }

        return $default;
    }

    public function removeRequestInput(string $key): void
    {
        unset($this->request[$key]);
    }

    /** @return array<string, mixed> */
    public function allRequest(): array
    {
        if (! empty($this->request)) {
            return $this->request;
        }

        if ($this->laravelRequest !== null) {
            return $this->laravelRequest->input();
        }

        return [];
    }

    /** @param array<string, mixed> $files */
    public function setFiles(array $files): void
    {
        $this->files = $this->normalizeFiles($files);
    }

    public function getFile(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function allFiles(): array
    {
        return $this->files;
    }

    public function setLaravelRequest(?Request $request): void
    {
        $this->laravelRequest = $request;
    }

    public function getLaravelRequest(): ?Request
    {
        if ($this->laravelRequest !== null) {
            return $this->laravelRequest;
        }

        if (function_exists('app')) {
            $app = app();
            if ($app->bound('request')) {
                /** @var mixed $request */
                $request = $app->make('request');
                if ($request instanceof Request) {
                    return $request;
                }
            }
        }

        return null;
    }

    private function ensureUserUpdateSetReference(): void
    {
        if (! array_key_exists('USERUPDATESET', $this->globals)) {
            $this->globals['USERUPDATESET'] = [];
        }
        $this->userUpdateSet = &$this->globals['USERUPDATESET'];
    }
}
