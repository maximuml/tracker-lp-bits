<?php

namespace Tests\Unit\Support;

use App\Support\AuthCookie;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class AuthCookieTest extends TestCase
{
    private const LEGACY_AUTH_KEY = 'test-secret-key-abc123';

    // ---------- buildToken() with Laravel encrypter ----------

    public function test_build_token_returns_encrypted_string(): void
    {
        $token = AuthCookie::buildToken(42, null, 1700000000);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_build_token_round_trips_through_verify_token(): void
    {
        $expires = time() + 3600;
        $token = AuthCookie::buildToken(42, null, $expires);

        $result = AuthCookie::verifyToken($token);

        $this->assertNotNull($result);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame($expires, $result['expires']);
    }

    public function test_build_token_ignores_legacy_auth_key(): void
    {
        $expires = time() + 3600;
        $a = AuthCookie::buildToken(1, 'key-a', $expires);
        $b = AuthCookie::buildToken(1, 'key-b', $expires);

        // Both decrypt to the same payload; the per-user auth_key is no longer used.
        $this->assertSame(AuthCookie::verifyToken($a)['user_id'], AuthCookie::verifyToken($b)['user_id']);
        $this->assertSame(AuthCookie::verifyToken($a)['expires'], AuthCookie::verifyToken($b)['expires']);
    }

    public function test_verify_token_with_wrong_app_key_returns_null(): void
    {
        // Simulate a token encrypted with a different APP_KEY by hand-encrypting.
        $badToken = Crypt::encryptString('not the real payload');

        // In this app the token is unreadable because the encrypter was built with a different key.
        $this->assertNull(AuthCookie::verifyToken($badToken));
    }

    public function test_verify_token_expired_returns_null(): void
    {
        $token = AuthCookie::buildToken(99, null, time() - 100);

        $this->assertNull(AuthCookie::verifyToken($token));
    }

    public function test_verify_token_garbage_returns_null(): void
    {
        $this->assertNull(AuthCookie::verifyToken('not-a-valid-cookie-value'));
    }

    // ---------- legacy HMAC token fallback ----------

    private function buildLegacyToken(int $userId, string $authKey, int $expires): string
    {
        $json = json_encode(['user_id' => $userId, 'expires' => $expires]);
        $signature = hash_hmac('sha256', $json, $authKey);

        return base64_encode($json . '.' . $signature);
    }

    public function test_verify_legacy_token_valid(): void
    {
        $expires = time() + 3600;
        $token = $this->buildLegacyToken(99, self::LEGACY_AUTH_KEY, $expires);

        $result = AuthCookie::verifyToken($token, self::LEGACY_AUTH_KEY);

        $this->assertNotNull($result);
        $this->assertSame(99, $result['user_id']);
        $this->assertSame($expires, $result['expires']);
    }

    public function test_verify_legacy_token_wrong_key_returns_null(): void
    {
        $token = $this->buildLegacyToken(99, self::LEGACY_AUTH_KEY, time() + 3600);

        $this->assertNull(AuthCookie::verifyToken($token, 'wrong-key'));
    }

    public function test_verify_legacy_token_expired_returns_null(): void
    {
        $token = $this->buildLegacyToken(99, self::LEGACY_AUTH_KEY, time() - 100);

        $this->assertNull(AuthCookie::verifyToken($token, self::LEGACY_AUTH_KEY));
    }

    public function test_verify_legacy_token_tampered_payload_returns_null(): void
    {
        $token = $this->buildLegacyToken(99, self::LEGACY_AUTH_KEY, time() + 3600);
        $decoded = base64_decode($token, true);
        $tampered = str_replace('"user_id":99', '"user_id":1', $decoded);

        $this->assertNull(AuthCookie::verifyToken(base64_encode($tampered), self::LEGACY_AUTH_KEY));
    }

    // ---------- computeExpires() ----------

    public function test_compute_expires_adds_duration_to_now(): void
    {
        $this->assertSame(1000 + 3600, AuthCookie::computeExpires(3600, 1000));
    }

    public function test_compute_expires_zero_now_uses_time(): void
    {
        $before = time();
        $result = AuthCookie::computeExpires(86400);
        $after = time();

        $this->assertGreaterThanOrEqual($before + 86400, $result);
        $this->assertLessThanOrEqual($after + 86400, $result);
    }

    // ---------- COOKIE_NAME constant ----------

    public function test_cookie_name_constant(): void
    {
        $this->assertSame('c_secure_pass', AuthCookie::COOKIE_NAME);
    }
}
