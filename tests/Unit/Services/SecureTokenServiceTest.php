<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SecureTokenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for SecureTokenService — CSPRNG token generation,
 * SHA-256 digest storage, atomic consumption, and legacy fallback.
 */
final class SecureTokenServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TEST_TABLE = 'password_recovery_tokens';

    private SecureTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SecureTokenService::class);
    }

    public function test_generate_returns_64_char_hex_token(): void
    {
        $token = $this->service->generate();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_generate_produces_unique_tokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $this->service->generate();
        }

        $this->assertSame(100, count(array_unique($tokens)));
    }

    public function test_digest_returns_sha256_hash(): void
    {
        $token = str_repeat('a', 64);
        $expected = hash('sha256', $token);
        $actual = $this->service->digest($token);

        $this->assertSame($expected, $actual);
        $this->assertSame(64, strlen($actual));
    }

    public function test_store_and_verify_roundtrip(): void
    {
        $token = $this->service->generate();
        $userId = 12345;

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $row = $this->service->verify(self::TEST_TABLE, $token);

        $this->assertNotNull($row);
        $this->assertSame($userId, (int) $row['user_id']);
        $this->assertNull($row['consumed_at']);
        $this->assertSame(0, (int) $row['revoked']);
    }

    public function test_consume_marks_token_as_consumed(): void
    {
        $token = $this->service->generate();

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => 100,
            'ip' => '127.0.0.1',
        ]);

        $row = $this->service->consume(self::TEST_TABLE, $token);
        $this->assertNotNull($row);
        $this->assertNotNull($row['consumed_at']);

        // Second consume should fail
        $row2 = $this->service->consume(self::TEST_TABLE, $token);
        $this->assertNull($row2);
    }

    public function test_consume_with_extra_update(): void
    {
        $token = $this->service->generate();

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => 200,
            'ip' => '127.0.0.1',
        ]);

        $row = $this->service->consume(self::TEST_TABLE, $token, [
            'consumed_at' => '2026-01-01 00:00:00',
        ]);

        $this->assertNotNull($row);
        $this->assertSame('2026-01-01 00:00:00', $row['consumed_at']);
    }

    public function test_verify_returns_null_for_nonexistent_token(): void
    {
        $fakeToken = str_repeat('0', 64);

        $row = $this->service->verify(self::TEST_TABLE, $fakeToken);

        $this->assertNull($row);
    }

    public function test_verify_returns_null_for_revoked_token(): void
    {
        $token = $this->service->generate();

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => 300,
            'ip' => '127.0.0.1',
        ]);

        $this->service->revoke(self::TEST_TABLE, $token);

        $row = $this->service->verify(self::TEST_TABLE, $token);
        $this->assertNull($row);
    }

    public function test_verify_returns_null_for_consumed_token(): void
    {
        $token = $this->service->generate();

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => 400,
            'ip' => '127.0.0.1',
        ]);

        $this->service->consume(self::TEST_TABLE, $token);

        $row = $this->service->verify(self::TEST_TABLE, $token);
        $this->assertNull($row);
    }

    public function test_verify_returns_null_for_expired_token(): void
    {
        $token = $this->service->generate();
        $digest = $this->service->digest($token);

        // Insert with past expiry
        DB::table(self::TEST_TABLE)->insert([
            'token_digest' => $digest,
            'user_id' => 500,
            'ip' => '127.0.0.1',
            'expires_at' => now()->subDay()->toDateTimeString(),
            'consumed_at' => null,
            'revoked' => 0,
            'created_at' => now()->toDateTimeString(),
        ]);

        $row = $this->service->verify(self::TEST_TABLE, $token);
        $this->assertNull($row);
    }

    public function test_consume_returns_null_for_expired_token(): void
    {
        $token = $this->service->generate();
        $digest = $this->service->digest($token);

        DB::table(self::TEST_TABLE)->insert([
            'token_digest' => $digest,
            'user_id' => 600,
            'ip' => '127.0.0.1',
            'expires_at' => now()->subDay()->toDateTimeString(),
            'consumed_at' => null,
            'revoked' => 0,
            'created_at' => now()->toDateTimeString(),
        ]);

        $row = $this->service->consume(self::TEST_TABLE, $token);
        $this->assertNull($row);
    }

    public function test_revoke_returns_true_for_existing_token(): void
    {
        $token = $this->service->generate();

        $this->service->store(self::TEST_TABLE, $token, [
            'user_id' => 700,
            'ip' => '127.0.0.1',
        ]);

        $result = $this->service->revoke(self::TEST_TABLE, $token);
        $this->assertTrue($result);
    }

    public function test_revoke_returns_false_for_nonexistent_token(): void
    {
        $fakeToken = str_repeat('0', 64);

        $result = $this->service->revoke(self::TEST_TABLE, $fakeToken);
        $this->assertFalse($result);
    }

    public function test_verify_legacy_finds_existing_hash(): void
    {
        $legacyHash = md5('legacy-token-test');

        DB::table('invites')->insert([
            'inviter' => 1,
            'invitee' => 'test@example.com',
            'hash' => $legacyHash,
            'time_invited' => now()->toDateTimeString(),
            'valid' => 1,
            'created_at' => now()->toDateTimeString(),
        ]);

        $row = $this->service->verifyLegacy('invites', 'hash', $legacyHash);

        $this->assertNotNull($row);
        $this->assertSame($legacyHash, $row['hash']);
    }

    public function test_verify_legacy_returns_null_for_nonexistent(): void
    {
        $row = $this->service->verifyLegacy('invites', 'hash', 'nonexistent-hash-12345678901234567890123456789012');

        $this->assertNull($row);
    }

    public function test_token_digest_not_equal_to_token(): void
    {
        $token = $this->service->generate();
        $digest = $this->service->digest($token);

        $this->assertNotSame($token, $digest);
    }
}
