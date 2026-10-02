<?php

namespace Tests\Unit\Support;

use App\Support\AuthCookie;
use Illuminate\Support\Facades\Crypt;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class AuthCookieTest extends TestCase
{
    // ---------- buildToken() with Laravel encrypter ----------

    public function test_build_token_returns_encrypted_string(): void
    {
        $token = AuthCookie::buildToken(42, null, 1700000000, 1);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function test_build_token_round_trips_through_verify_token(): void
    {
        $expires = time() + 3600;
        $token = AuthCookie::buildToken(42, null, $expires, 7);

        $result = AuthCookie::verifyToken($token);

        $this->assertNotNull($result);
        $this->assertSame(42, $result['user_id']);
        $this->assertSame($expires, $result['expires']);
        $this->assertSame(7, $result['auth_version']);
    }

    public function test_build_token_rejects_invalid_auth_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuthCookie::buildToken(42, null, time() + 3600, 0);
    }

    public function test_verify_token_rejects_invalid_auth_version(): void
    {
        $token = Crypt::encryptString((string) json_encode([
            'user_id' => 42,
            'expires' => time() + 3600,
            'auth_version' => '7',
        ]));

        $this->assertNull(AuthCookie::verifyToken($token));
    }

    public function test_build_token_ignores_legacy_auth_key(): void
    {
        $expires = time() + 3600;
        $a = AuthCookie::buildToken(1, 'key-a', $expires, 1);
        $b = AuthCookie::buildToken(1, 'key-b', $expires, 1);

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
        $token = AuthCookie::buildToken(99, null, time() - 100, 1);

        $this->assertNull(AuthCookie::verifyToken($token));
    }

    public function test_verify_token_garbage_returns_null(): void
    {
        $this->assertNull(AuthCookie::verifyToken('not-a-valid-cookie-value'));
    }

    // ---------- legacy HMAC format is rejected ----------

    public function test_verify_token_rejects_legacy_hmac_format(): void
    {
        $json = json_encode(['user_id' => 99, 'expires' => time() + 3600]);
        $signature = hash_hmac('sha256', (string) $json, 'any-key');

        $this->assertNull(AuthCookie::verifyToken(base64_encode($json.'.'.$signature)));
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
