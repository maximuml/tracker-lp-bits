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
    /**
     * @param  array<string, mixed>|null  $user  Current user row (`$GLOBALS['CURUSER']`).
     * @param  array<string, string>  $lang  Loaded language strings (`$GLOBALS['lang_functions']`).
     * @param  object|null  $cache  Legacy Redis cache wrapper (`$GLOBALS['Cache']`).
     * @param  array<string, mixed>  $request  Merged request data (`$_POST` + `$_GET`).
     * @param  array<string, string>  $cookies  `$_COOKIE`.
     * @param  array<string, mixed>  $registration  Settings: `invitesystem`, `registration`, `maxusers`, `maxip`.
     * @param  int|null  $langId  Language id derived from the language cookie.
     */
    public function __construct(
        public ?array $user,
        public array $lang,
        public ?object $cache,
        public string $ip,
        public ?string $requestUri,
        public array $request,
        public array $cookies,
        public int $maxLoginAttempts,
        public bool $captchaEnabled,
        public array $registration,
        public ?int $langId,
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
}
