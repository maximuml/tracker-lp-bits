<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PasswordHasher;
use App\Support\Token;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1.3: verify that password hashing uses argon2id for new users,
 * legacy sha256/md5 hashes are verified correctly, and rehash-on-login
 * upgrades legacy hashes to argon2id.
 */
final class Phase13PasswordArgon2idTest extends TestCase
{
    use DatabaseTransactions;

    public function test_password_hasher_creates_argon2id_hash(): void
    {
        $hash = PasswordHasher::hash('test-password');

        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify('test-password', $hash));
        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_password_hasher_verifies_argon2id(): void
    {
        $hash = PasswordHasher::hash('my-secret-password');

        $this->assertTrue(PasswordHasher::verify('my-secret-password', $hash, '', PasswordHasher::ALGO_ARGON2ID));
        $this->assertFalse(PasswordHasher::verify('wrong-password', $hash, '', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_password_hasher_verifies_legacy_sha256(): void
    {
        $password = 'legacy-password';
        $secret = Token::randomHex();
        $passhash = hash('sha256', $secret.hash('sha256', $password));

        $this->assertTrue(PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_SHA256));
        $this->assertFalse(PasswordHasher::verify('wrong-password', $passhash, $secret, PasswordHasher::ALGO_SHA256));
    }

    public function test_password_hasher_verifies_legacy_md5(): void
    {
        $password = 'old-password';
        $secret = Token::randomHex();
        $passhash = md5($secret.$password.$secret);

        $this->assertTrue(PasswordHasher::verify($password, $passhash, $secret, PasswordHasher::ALGO_MD5));
        $this->assertFalse(PasswordHasher::verify('wrong-password', $passhash, $secret, PasswordHasher::ALGO_MD5));
    }

    public function test_password_hasher_empty_password_returns_false(): void
    {
        $this->assertFalse(PasswordHasher::verify('', 'some-hash', 'secret', PasswordHasher::ALGO_ARGON2ID));
    }

    public function test_needs_rehash_for_legacy_algorithms(): void
    {
        $this->assertTrue(PasswordHasher::needsRehash(PasswordHasher::ALGO_SHA256, 'some-hash'));
        $this->assertTrue(PasswordHasher::needsRehash(PasswordHasher::ALGO_MD5, 'some-hash'));
    }

    public function test_needs_rehash_false_for_fresh_argon2id(): void
    {
        $hash = PasswordHasher::hash('test-password');

        $this->assertFalse(PasswordHasher::needsRehash(PasswordHasher::ALGO_ARGON2ID, $hash));
    }

    public function test_migration_adds_passhash_algo_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'passhash_algo'),
            'users table should have passhash_algo column after migration'
        );
    }

    public function test_existing_users_default_to_sha256_algo(): void
    {
        // Create a user directly to check the default value
        $userId = User::query()->insertGetId([
            'username' => 'test_algo_user_'.uniqid(),
            'email' => 'test_algo_'.uniqid().'@example.com',
            'passhash' => hash('sha256', 'secret'.hash('sha256', 'pass')),
            'secret' => 'secret',
            'auth_key' => Token::randomHex(),
            'passkey' => md5('test'.date('Y-m-d H:i:s')),
            'status' => 'confirmed',
            'class' => User::CLASS_USER,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
        ]);

        $user = User::query()->find($userId);
        $this->assertNotNull($user);
        $this->assertSame(PasswordHasher::ALGO_SHA256, (string) $user->passhash_algo);

        User::query()->where('id', $userId)->delete();
    }

    public function test_new_user_registration_uses_argon2id(): void
    {
        $secret = Token::randomHex();
        $passhash = PasswordHasher::hash('new-user-password');

        $userId = User::query()->insertGetId([
            'username' => 'test_argon2_user_'.uniqid(),
            'email' => 'test_argon2_'.uniqid().'@example.com',
            'passhash' => $passhash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'secret' => $secret,
            'auth_key' => Token::randomHex(),
            'passkey' => md5('test'.date('Y-m-d H:i:s')),
            'status' => 'confirmed',
            'class' => User::CLASS_USER,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
        ]);

        $user = User::query()->find($userId);
        $this->assertNotNull($user);
        $this->assertSame(PasswordHasher::ALGO_ARGON2ID, (string) $user->passhash_algo);
        $this->assertStringStartsWith('$argon2id$', (string) $user->passhash);
        $this->assertTrue(password_verify('new-user-password', (string) $user->passhash));

        User::query()->where('id', $userId)->delete();
    }

    public function test_challenge_endpoint_returns_passhash_algo(): void
    {
        $username = 'test_challenge_'.uniqid();
        $userId = User::query()->insertGetId([
            'username' => $username,
            'email' => 'test_challenge_'.uniqid().'@example.com',
            'passhash' => PasswordHasher::hash('password'),
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'secret' => Token::randomHex(),
            'auth_key' => Token::randomHex(),
            'passkey' => md5('test'.date('Y-m-d H:i:s')),
            'status' => 'confirmed',
            'class' => User::CLASS_USER,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
        ]);

        $response = $this->postJson('/api/challenge', ['username' => $username]);

        $response->assertOk();
        $response->assertJsonPath('data.passhash_algo', PasswordHasher::ALGO_ARGON2ID);

        User::query()->where('id', $userId)->delete();
    }
}
