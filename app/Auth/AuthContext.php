<?php

declare(strict_types=1);

namespace App\Auth;

use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Locale;
use App\Support\Network;
use App\Support\RequestContext;
use App\Support\RequestValues;

/**
 * Context bundle for the access-gate checks.
 *
 * Collects user row, request data and settings so the gate helpers
 * stay testable and decoupled from global state.
 */
final class AuthContext
{
    private ?int $langIdCache = null;

    /**
     * @param  array<string, mixed>|null  $user  Current user row.
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
     * Build a context from the current request-scoped state.
     */
    public static function current(): self
    {
        $script = '';
        if (\function_exists('nexus')) {
            $script = RequestContext::instance()->getScript();
        } else {
            $scriptFile = RequestValues::serverValue('SCRIPT_FILENAME', '');
            $script = basename($scriptFile);
            if (str_contains($script, '.')) {
                $script = strstr($script, '.', true) ?: '';
            }
        }

        return new self(
            user: CurrentUser::instance()->get(),
            cache: NexusCache::instance(),
            ip: \function_exists('getip') ? Network::clientIp((bool) true) : Network::clientIp(),
            requestUri: RequestValues::serverValue('REQUEST_URI'),
            requestBody: request()->post(),
            queryParams: request()->query(),
            request: array_merge(request()->post(), request()->query()),
            cookies: request()->cookies->all(),
            maxLoginAttempts: SiteConfig::current()->security->maxLoginAttempts(0),
            captchaEnabled: SiteConfig::current()->security->captchaRequired(),
            registration: [
                'invitesystem' => SiteConfig::current()->main->inviteSystem(true) ? 'yes' : 'no',
                'registration' => SiteConfig::current()->main->registration(true) ? 'yes' : 'no',
                'maxusers' => SiteConfig::current()->main->maxUsers(0),
                'maxip' => SiteConfig::current()->security->maxIp(0),
            ],
            langFolder: RequestValues::cookieValue('c_lang_folder'),
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
