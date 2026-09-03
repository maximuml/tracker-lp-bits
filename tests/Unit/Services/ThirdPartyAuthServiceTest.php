<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ThirdPartyAuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for ThirdPartyAuthService — v2 HMAC-SHA256 protocol
 * and legacy backward compatibility for IYUU, NAS Tools, AMMDS.
 */
final class ThirdPartyAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ThirdPartyAuthService $service;

    private const SECRET = 'test-integration-secret-key-for-hmac';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ThirdPartyAuthService::class);
    }

    public function test_canonical_payload_is_deterministic(): void
    {
        $canonical1 = $this->service->canonicalPayload('iyuu', 123, 'abc123', 1700000000, 'nonce456');
        $canonical2 = $this->service->canonicalPayload('iyuu', 123, 'abc123', 1700000000, 'nonce456');

        $this->assertSame($canonical1, $canonical2);
        $this->assertStringContainsString('iyuu', $canonical1);
        $this->assertStringContainsString('123', $canonical1);
        $this->assertStringContainsString('nonce456', $canonical1);
        $this->assertStringContainsString(ThirdPartyAuthService::VERSION, $canonical1);
    }

    public function test_canonical_payload_differs_by_provider(): void
    {
        $c1 = $this->service->canonicalPayload('iyuu', 1, 'h', 100, 'n');
        $c2 = $this->service->canonicalPayload('nastools', 1, 'h', 100, 'n');

        $this->assertNotSame($c1, $c2);
    }

    public function test_verify_v2_succeeds_with_valid_signature(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('a', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        $result = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_verify_v2_fails_with_wrong_signature(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('b', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $result = $this->service->verifyV2(
            'iyuu',
            $user->id,
            $user->passkey,
            time(),
            bin2hex(random_bytes(16)),
            str_repeat('0', 64),
            self::SECRET,
        );

        $this->assertNull($result);
    }

    public function test_verify_v2_fails_with_expired_timestamp(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('c', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time() - 600;
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        $result = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNull($result);
    }

    public function test_verify_v2_fails_with_future_timestamp(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('d', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time() + 600;
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        $result = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNull($result);
    }

    public function test_verify_v2_fails_with_reused_nonce(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('e', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        // First call should succeed
        $result1 = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);
        $this->assertNotNull($result1);

        // Second call with same nonce should fail (replay)
        $result2 = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);
        $this->assertNull($result2);
    }

    public function test_verify_v2_fails_with_wrong_passkey(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('f', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        // Use wrong passkey in verification
        $result = $this->service->verifyV2('iyuu', $user->id, str_repeat('0', 32), $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNull($result);
    }

    public function test_verify_v2_fails_with_nonexistent_user(): void
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', str_repeat('a', 32));
        $canonical = $this->service->canonicalPayload('iyuu', 999999, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, self::SECRET);

        $result = $this->service->verifyV2('iyuu', 999999, str_repeat('a', 32), $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNull($result);
    }

    public function test_verify_v2_fails_with_wrong_secret(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('1', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);
        $canonical = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $signature = hash_hmac('sha256', $canonical, 'wrong-secret');

        $result = $this->service->verifyV2('iyuu', $user->id, $user->passkey, $timestamp, $nonce, $signature, self::SECRET);

        $this->assertNull($result);
    }

    public function test_verify_v2_different_producers_have_different_signatures(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('2', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $passkeyHash = hash('sha256', $user->passkey);

        $canonicalIyuu = $this->service->canonicalPayload('iyuu', $user->id, $passkeyHash, $timestamp, $nonce);
        $canonicalNas = $this->service->canonicalPayload('nastools', $user->id, $passkeyHash, $timestamp, $nonce);

        $sigIyuu = hash_hmac('sha256', $canonicalIyuu, self::SECRET);
        $sigNas = hash_hmac('sha256', $canonicalNas, self::SECRET);

        $this->assertNotSame($sigIyuu, $sigNas);
    }

    public function test_verify_legacy_iyuu_succeeds_with_valid_md5(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('3', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $token = 'iyuu-token-test';
        $expected = md5($token.$user->id.sha1($user->passkey).self::SECRET);

        $result = $this->service->verifyLegacyIyuu($token, $user->id, $expected, self::SECRET);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_verify_legacy_iyuu_fails_with_wrong_md5(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('4', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $result = $this->service->verifyLegacyIyuu('token', $user->id, 'wrong-verity', self::SECRET);

        $this->assertNull($result);
    }

    public function test_audit_writes_log_without_secrets(): void
    {
        // This test just verifies the audit method doesn't throw.
        // The log output is verified by SensitiveDataRedactorTest.
        $this->expectNotToPerformAssertions();

        $this->service->audit('iyuu', 123, 'test_event', [
            'nonce' => 'test-nonce',
            'server_time' => time(),
        ]);
    }
}
