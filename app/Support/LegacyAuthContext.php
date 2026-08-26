<?php

namespace App\Support;

use App\Support\Cache\LegacyRedisCache;
use Nexus\Nexus;

/**
 * Context bundle for legacy authentication helpers.
 *
 * `LegacyAuth` no longer reads `$GLOBALS` or super-globals directly;
 * callers (the procedural wrappers in `include/functions.php`) collect
 * the required values and pass them in this object. This makes the
 * auth helpers testable and decouples them from global state.
 *
 * The factory that builds this object from legacy globals lives in
 * `include/functions.php` so `App\Support` stays free of `$_GET`/`$_POST`
 * and `$GLOBALS`.
 */
final class LegacyAuthContext
{
    private ?int $langIdCache = null;

    /**
     * @param  array<string, mixed>|null  $user  Current user row.
     * @param  array<string, string>  $lang  Loaded language strings.
     * @param  object|null  $cache  Legacy Redis cache wrapper.
     * @param  array<string, mixed>  $requestBody  POST data.
     * @param  array<string, mixed>  $queryParams  Query data.
     * @param  array<string, mixed>  $request  Merged POST + query data.
     * @param  array<string, string>  $cookies  Cookie data.
     * @param  array<string, mixed>  $registration  Settings: `invitesystem`, `registration`, `maxusers`, `maxip`.
     * @param  string|null  $langFolder  Raw language folder from the language cookie.
     */
    public function __construct(
        public ?array $user,
        public array $lang,
        public ?object $cache,
        public string $ip,
        public ?string $requestUri,
        public array $requestBody,
        public array $queryParams,
        public array $request,
        public array $cookies,
        public int $maxLoginAttempts,
        public bool $captchaEnabled,
        public array $registration,
        public ?string $langFolder,
        public int $moderatorClass,
        public string $script,
    ) {}

    /**
     * Build a context from the current {@see SupportContext}.
     * Replaces the legacy `legacy_auth_context()` helper for callers that
     * already live inside the modern support layer.
     */
    public static function fromSupportContext(): self
    {
        $script = '';
        if (\function_exists('nexus')) {
            $script = Nexus::instance()->getScript();
        } else {
            $scriptFile = SupportContext::getServerValue('SCRIPT_FILENAME', '');
            $script = basename($scriptFile);
            if (str_contains($script, '.')) {
                $script = strstr($script, '.', true);
            }
        }

        return new self(
            user: app(CurrentUser::class)->get(),
            lang: SupportContext::getLangFunctions(),
            cache: app(LegacyRedisCache::class),
            ip: \function_exists('getip') ? Network::clientIp((bool) true) : Network::clientIp(),
            requestUri: SupportContext::getServerValue('REQUEST_URI'),
            requestBody: SupportContext::allPost(),
            queryParams: SupportContext::allQuery(),
            request: array_merge(SupportContext::allPost(), SupportContext::allQuery()),
            cookies: SupportContext::allCookie(),
            maxLoginAttempts: (int) SupportContext::getGlobal('maxloginattempts', 0),
            captchaEnabled: SupportContext::getGlobal('iv', '') === 'yes',
            registration: [
                'invitesystem' => (string) SupportContext::getGlobal('invitesystem', ''),
                'registration' => (string) SupportContext::getGlobal('registration', ''),
                'maxusers' => (int) SupportContext::getGlobal('maxusers', 0),
                'maxip' => (int) SupportContext::getGlobal('maxip', 0),
            ],
            langFolder: SupportContext::getCookieValue('c_lang_folder'),
            moderatorClass: defined('UC_MODERATOR') ? (int) \constant('UC_MODERATOR') : 0,
            script: $script,
        );
    }

    public function isLoggedIn(): bool
    {
        return $this->user !== null && ! empty($this->user['id']);
    }

    public function userClass(): int|string
    {
        return $this->user['class'] ?? '';
    }

    public function isModerator(): bool
    {
        return (int) ($this->user['class'] ?? 0) >= $this->moderatorClass;
    }

    /**
     * Resolve the language id from the cookie folder lazily. This avoids
     * an uncached database query for callers that do not need it.
     */
    public function langId(): int
    {
        if ($this->langIdCache !== null) {
            return $this->langIdCache;
        }

        if ($this->langFolder === null || $this->langFolder === '') {
            return $this->langIdCache = 0;
        }

        $folder = Locale::folderFromCookie($this->langFolder);

        return $this->langIdCache = Locale::idFromFolder($folder);
    }
}
