<?php

namespace Tests\Unit\Support;

use App\Support\AuthCookie;
use PHPUnit\Framework\TestCase;

final class AuthCookieTest extends TestCase
{
    private const AUTH_KEY = 'test-secret-key-abc123';

    // ---------- buildToken() ----------

    public function test_build_token_returns_base64_string(): void
    {
        $token = AuthCookie::buildToken(42, self::AUTH_KEY, 1700000000);
        // Must be valid base64
        $this->assertNotFalse(base64_decode($token, true));
    }

    public function test_build_token_contains_user_id_and_expires(): void
    {
        $expires = 1700000000;
        $token = AuthCookie::buildToken(42, self::AUTH_KEY, $expires);
        $decoded = base64_decode($token, true);

        // Token format: json.signature
        $dotPos = strrpos($decoded, '.');
        $json = substr($decoded, 0, $dotPos);
        $data = json_decode($json, true);

        $this->assertSame(42, $data['user_id']);
        $this->assertSame($expires, $data['expires']);
    }

    public function test_build_token_signature_uses_hmac_sha256(): void
    {
        $expires = 1700000000;
        $token = AuthCookie::buildToken(42, self::AUTH_KEY, $expires);
        $decoded = base64_decode($token, true);

        $dotPos = strrpos($decoded, '.');
        $json = substr($decoded, 0, $dotPos);
        $signature = substr($decoded, $dotPos + 1);

        $expectedSig = hash_hmac('sha256', $json, self::AUTH_KEY);
        $this->assertSame($expectedSig, $signature);
    }

    public function test_build_token_throws_on_empty_auth_key(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('auth_key is empty');
        AuthCookie::buildToken(1, '', 1700000000);
    }

    public function test_build_token_different_keys_produce_different_tokens(): void
    {
        $a = AuthCookie::buildToken(1, 'key-a', 1700000000);
        $b = AuthCookie::buildToken(1, 'key-b', 1700000000);
        $this->assertNotSame($a, $b);
    }

    // ---------- verifyToken() ----------

    public function test_verify_token_valid(): void
    {
        $expires = time() + 3600;
        $token = AuthCookie::buildToken(99, self::AUTH_KEY, $expires);
        $result = AuthCookie::verifyToken($token, self::AUTH_KEY);

        $this->assertNotNull($result);
        $this->assertSame(99, $result['user_id']);
        $this->assertSame($expires, $result['expires']);
    }

    public function test_verify_token_wrong_key_returns_null(): void
    {
        $token = AuthCookie::buildToken(99, self::AUTH_KEY, time() + 3600);
        $this->assertNull(AuthCookie::verifyToken($token, 'wrong-key'));
    }

    public function test_verify_token_expired_returns_null(): void
    {
        $token = AuthCookie::buildToken(99, self::AUTH_KEY, time() - 100);
        $this->assertNull(AuthCookie::verifyToken($token, self::AUTH_KEY));
    }

    public function test_verify_token_garbage_returns_null(): void
    {
        $this->assertNull(AuthCookie::verifyToken('not-valid-base64!!!', self::AUTH_KEY));
    }

    public function test_verify_token_tampered_payload_returns_null(): void
    {
        $token = AuthCookie::buildToken(99, self::AUTH_KEY, time() + 3600);
        $decoded = base64_decode($token, true);

        // Tamper: change user_id in the JSON
        $tampered = str_replace('"user_id":99', '"user_id":1', $decoded);
        $tamperedToken = base64_encode($tampered);

        $this->assertNull(AuthCookie::verifyToken($tamperedToken, self::AUTH_KEY));
    }

    public function test_verify_token_missing_dot_returns_null(): void
    {
        $this->assertNull(AuthCookie::verifyToken(base64_encode('nodothere'), self::AUTH_KEY));
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
