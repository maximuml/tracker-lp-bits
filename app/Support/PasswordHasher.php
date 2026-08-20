<?php

namespace App\Support;

/**
 * Centralised password hashing utility.
 *
 * Supports three algorithms:
 * - 'argon2id': PHP password_hash(PASSWORD_ARGON2ID) — the modern default.
 * - 'sha256':   Legacy NexusPHP hash = sha256(secret + sha256(password)).
 * - 'md5':      Ancient legacy hash = md5(secret + password + secret).
 *
 * On successful legacy verification, callers should rehash to argon2id
 * via {@see PasswordHasher::hash()} and update the user's `passhash_algo`.
 */
final class PasswordHasher
{
    public const ALGO_ARGON2ID = 'argon2id';

    public const ALGO_SHA256 = 'sha256';

    public const ALGO_MD5 = 'md5';

    /**
     * Hash a plaintext password using argon2id.
     */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a plaintext password against a stored hash.
     *
     * @param  string  $password  Plaintext password from the user.
     * @param  string  $passhash  Stored hash from the database.
     * @param  string  $secret  User's per-account secret (used by legacy algorithms).
     * @param  string  $algo  Algorithm identifier: 'argon2id', 'sha256', or 'md5'.
     */
    public static function verify(string $password, string $passhash, string $secret, string $algo): bool
    {
        if ($password === '' || $passhash === '') {
            return false;
        }

        return match ($algo) {
            self::ALGO_ARGON2ID => password_verify($password, $passhash),
            self::ALGO_SHA256 => self::verifySha256($password, $passhash, $secret),
            self::ALGO_MD5 => self::verifyMd5($password, $passhash, $secret),
            default => self::verifySha256($password, $passhash, $secret),
        };
    }

    /**
     * Check if the stored hash should be rehashed to argon2id.
     */
    public static function needsRehash(string $algo, string $passhash): bool
    {
        if ($algo !== self::ALGO_ARGON2ID) {
            return true;
        }

        return password_needs_rehash($passhash, PASSWORD_ARGON2ID);
    }

    /**
     * Legacy sha256 verification: sha256(secret + sha256(password)).
     *
     * Uses a challenge-response HMAC comparison to avoid timing leaks.
     */
    private static function verifySha256(string $password, string $passhash, string $secret): bool
    {
        $passwordHash = hash('sha256', $secret.hash('sha256', $password));
        $challenge = Token::randomHex();
        $expected = hash_hmac('sha256', $passhash, $challenge);
        $response = hash_hmac('sha256', $passwordHash, $challenge);

        return hash_equals($expected, $response);
    }

    /**
     * Ancient md5 verification: md5(secret + password + secret).
     */
    private static function verifyMd5(string $password, string $passhash, string $secret): bool
    {
        $oldMd5 = md5($secret.$password.$secret);

        return hash_equals($oldMd5, $passhash);
    }
}
