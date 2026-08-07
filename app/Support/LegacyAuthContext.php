<?php

namespace App\Support;

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
    ) {
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
