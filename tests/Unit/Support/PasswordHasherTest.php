<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the centralised PasswordHasher utility.
 *
 * Covers argon2id hashing/verification, legacy sha256 and md5
 * verification, rehash detection, and edge cases.
 */
final class PasswordHasherTest extends TestCase
{
    public function test_hash_returns_argon2id_hash(): void
    {
        $hash = PasswordHasher::hash('mySecretPassword');

        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_hash_generates_unique_hashes(): void
    {
        $hash1 = PasswordHasher::hash('password');
        $hash2 = PasswordHasher::hash('password');

        $this->assertNotSame($hash1, $hash2, 'Argon2id hashes should be unique due to random salt');
    }

    public function test_verify_argon2id_correct_password(): void
    {
        $password = 'correctHorseBatteryStaple';
        $hash = PasswordHasher::hash($password);

        $this->assertTrue(PasswordHasher::verify($password, $hash, '', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_verify_argon2id_wrong_password(): void
    {
        $hash = PasswordHasher::hash('correctPassword');

        $this->assertFalse(PasswordHasher::verify('wrongPassword', $hash, '', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_verify_sha256_correct_password(): void
    {
        $password = 'legacyPassword';
        $secret = 'abc123secret';
        $passhash = hash('sha256', $secret.hash('sha256', $password));

        $this->assertTrue(PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_SHA256));
    }

    public function test_verify_sha256_wrong_password(): void
    {
        $secret = 'abc123secret';
        $passhash = hash('sha256', $secret.hash('sha256', 'correctPassword'));

        $this->assertFalse(PasswordHasher::verify('wrongPassword', $passhash, $secret, PasswordHasher::ALGO_SHA256));
    }

    public function test_verify_sha256_wrong_secret(): void
    {
        $password = 'legacyPassword';
        $passhash = hash('sha256', 'correctSecret'.hash('sha256', $password));

        $this->assertFalse(PasswordHasher::verify($password, $passhash, 'wrongSecret', PasswordHasher::ALGO_SHA256));
    }

    public function test_verify_md5_correct_password(): void
    {
        $password = 'ancientPassword';
        $secret = 'md5secret';
        $passhash = md5($secret.$password.$secret);

        $this->assertTrue(PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_MD5));
    }

    public function test_verify_md5_wrong_password(): void
    {
        $secret = 'md5secret';
        $passhash = md5($secret.'correctPassword'.$secret);

        $this->assertFalse(PasswordHasher::verify('wrongPassword', $passhash, $secret, PasswordHasher::ALGO_MD5));
    }

    public function test_verify_empty_password_returns_false(): void
    {
        $this->assertFalse(PasswordHasher::verify('', 'somehash', 'secret', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_verify_empty_hash_returns_false(): void
    {
        $this->assertFalse(PasswordHasher::verify('password', '', 'secret', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_verify_unknown_algo_falls_back_to_sha256(): void
    {
        $password = 'fallbackPassword';
        $secret = 'fallbackSecret';
        $passhash = hash('sha256', $secret.hash('sha256', $password));

        $this->assertTrue(PasswordHasher::verify($password, $passhash, $secret, 'unknown_algo'));
    }

    public function test_needs_rehash_returns_true_for_sha256(): void
    {
        $this->assertTrue(PasswordHasher::needsRehash(PasswordHasher::ALGO_SHA256, 'somehash'));
    }

    public function test_needs_rehash_returns_true_for_md5(): void
    {
        $this->assertTrue(PasswordHasher::needsRehash(PasswordHasher::ALGO_MD5, 'somehash'));
    }

    public function test_needs_rehash_returns_false_for_fresh_argon2id(): void
    {
        $hash = PasswordHasher::hash('password');

        $this->assertFalse(PasswordHasher::needsRehash(PasswordHasher::ALGO_ARGON2ID, $hash));
    }

    public function test_needs_rehash_returns_true_for_invalid_argon2id_hash(): void
    {
        $this->assertTrue(PasswordHasher::needsRehash(PasswordHasher::ALGO_ARGON2ID, 'invalid_hash_string'));
    }

    public function test_algo_constants_are_distinct_strings(): void
    {
        $this->assertSame('argon2id', PasswordHasher::ALGO_ARGON2ID);
        $this->assertSame('sha256', PasswordHasher::ALGO_SHA256);
        $this->assertSame('md5', PasswordHasher::ALGO_MD5);

        $this->assertNotSame(PasswordHasher::ALGO_ARGON2ID, PasswordHasher::ALGO_SHA256);
        $this->assertNotSame(PasswordHasher::ALGO_SHA256, PasswordHasher::ALGO_MD5);
        $this->assertNotSame(PasswordHasher::ALGO_ARGON2ID, PasswordHasher::ALGO_MD5);
    }
}
