<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Support\PasswordHasher;
use App\Support\Security\PasskeyGenerator;
use App\Support\Token;

/**
 * Handles password hashing and validation during registration and confirmation.
 */
class PasswordSetup
{
    private const MIN_PASSWORD_LENGTH = 6;

    private const MAX_PASSWORD_LENGTH = 40;

    public function __construct(
        private readonly PasskeyGenerator $passkeyGenerator,
    ) {}

    /**
     * @param  array<string, string>  $lang
     */
    public function validate(string $password, string $passAgain, string $username, array $lang): void
    {
        if ($password !== $passAgain) {
            throw new AuthenticationException($this->msg($lang, 'std_passwords_unmatched', 'The passwords didn\'t match!'));
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new AuthenticationException($this->msg($lang, 'std_password_too_short', 'Sorry, password is too short (min is 6 chars).'));
        }

        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw new AuthenticationException($this->msg($lang, 'std_password_too_long', 'Sorry, password is too long (max is 40 chars).'));
        }

        if ($password === $username) {
            throw new AuthenticationException($this->msg($lang, 'std_password_equals_username', 'Sorry, password cannot be same as user name.'));
        }
    }

    /**
     * Hash a password for a new user, including a fresh tracker passkey.
     *
     * @return array{passhash: string, passhash_algo: string, secret: string, passkey: string}
     */
    public function forNewUser(string $password): array
    {
        $hash = $this->hash($password);

        return array_merge($hash, ['passkey' => $this->passkeyGenerator->generate()]);
    }

    /**
     * Hash a password for a confirmation resend flow.
     *
     * @return array{passhash: string, passhash_algo: string, secret: string}
     */
    public function forResend(string $password): array
    {
        return $this->hash($password);
    }

    /**
     * @return array{passhash: string, passhash_algo: string, secret: string}
     */
    private function hash(string $password): array
    {
        return [
            'passhash' => PasswordHasher::hash($password),
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'secret' => Token::randomHex(),
        ];
    }

    /**
     * @param  array<string, string>  $lang
     */
    private function msg(array $lang, string $key, string $fallback): string
    {
        return (string) ($lang[$key] ?? $fallback);
    }
}
