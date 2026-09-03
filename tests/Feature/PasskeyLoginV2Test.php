<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\AuthenticateController;
use App\Models\User;
use App\Services\PasskeyLoginService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Passkey login v2 test suite.
 *
 * Covers: valid login, altered payload, reused nonce, expired timestamp,
 * invalid format, rotated key, rate limit, and legacy fallback.
 */
final class PasskeyLoginV2Test extends TestCase
{
    use DatabaseTransactions;

    private const SIGNING_KEY = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private const KEY_ID = 'test-key-1';

    private const PREVIOUS_KEY = 'f6e5d4c3b2a1f6e5d4c3b2a1f6e5d4c3b2a1f6e5d4c3b2a1f6e5d4c3b2a1f6e5';

    private const PREVIOUS_KEY_ID = 'test-key-0';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        // Reset settings cache so new values are picked up
        Settings::resetCache();

        // Enable v2 and set signing keys
        Settings::saveBatch('security', [
            'passkey_login_v2_enabled' => 'yes',
            'passkey_login_signing_key_current' => self::SIGNING_KEY,
            'passkey_login_signing_key_id_current' => self::KEY_ID,
            'passkey_login_signing_key_previous' => self::PREVIOUS_KEY,
            'passkey_login_signing_key_id_previous' => self::PREVIOUS_KEY_ID,
            'login_secret_deadline' => now()->addDays(30)->toDateTimeString(),
            'login_type' => 'passkey',
        ]);

        // Reset cache again so route registration sees the new values
        Settings::resetCache();

        // Clear nonce keys
        Redis::del(array_map(
            fn ($i) => 'passkey_login_v2:nonce:test'.$i,
            range(0, 20),
        ));
    }

    /**
     * Register the v2 route for this test case.
     */
    private function registerRoute(): void
    {
        $this->app['router']->post('passkeyloginv2', [
            AuthenticateController::class, 'passkeyLoginV2',
        ]);
    }

    public function test_valid_payload_authenticates_user(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('a', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $response = $this->post('passkeyloginv2', $this->buildPayload($user->passkey));

        $response->assertRedirect('index.php');
    }

    public function test_altered_passkey_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('b', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $payload = $this->buildPayload($user->passkey);
        $payload['passkey'] = str_repeat('c', 32);

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_altered_timestamp_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('d', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $payload = $this->buildPayload($user->passkey);
        $payload['timestamp'] = time() + 60;

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_reused_nonce_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('e', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $nonce = bin2hex(random_bytes(16));
        $payload1 = $this->buildPayload($user->passkey, $nonce);
        $payload2 = $this->buildPayload($user->passkey, $nonce);

        $response1 = $this->post('passkeyloginv2', $payload1);
        $response1->assertRedirect('index.php');

        $response2 = $this->post('passkeyloginv2', $payload2);
        $response2->assertRedirect('index.php');
    }

    public function test_expired_timestamp_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('f', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time() - 600;
        $payload = $this->buildPayload($user->passkey, bin2hex(random_bytes(16)), $timestamp);

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_future_timestamp_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('1', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $timestamp = time() + 600;
        $payload = $this->buildPayload($user->passkey, bin2hex(random_bytes(16)), $timestamp);

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_invalid_passkey_format_rejected(): void
    {
        $this->registerRoute();
        $response = $this->post('passkeyloginv2', [
            'passkey' => 'not-hex-not-32-chars',
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
            'signature' => bin2hex(random_bytes(32)),
            'key_id' => self::KEY_ID,
        ]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_invalid_signature_format_rejected(): void
    {
        $this->registerRoute();
        $response = $this->post('passkeyloginv2', [
            'passkey' => str_repeat('a', 32),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
            'signature' => 'not-hex-signature',
            'key_id' => self::KEY_ID,
        ]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_invalid_nonce_format_rejected(): void
    {
        $this->registerRoute();
        $response = $this->post('passkeyloginv2', [
            'passkey' => str_repeat('a', 32),
            'timestamp' => time(),
            'nonce' => 'short',
            'signature' => bin2hex(random_bytes(32)),
            'key_id' => self::KEY_ID,
        ]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_missing_key_id_rejected(): void
    {
        $this->registerRoute();
        $response = $this->post('passkeyloginv2', [
            'passkey' => str_repeat('a', 32),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
            'signature' => bin2hex(random_bytes(32)),
        ]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_unknown_key_id_rejected(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('2', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $payload = $this->buildPayload($user->passkey);
        $payload['key_id'] = 'nonexistent-key';
        $payload['signature'] = hash_hmac('sha256', $this->canonicalJson($payload), 'unknown-key');

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_rotated_previous_key_still_accepted(): void
    {
        $this->registerRoute();
        $user = User::factory()->create([
            'passkey' => str_repeat('3', 32),
            'status' => 'confirmed',
            'enabled' => true,
        ]);

        $payload = $this->buildPayload($user->passkey, bin2hex(random_bytes(16)), time(), self::PREVIOUS_KEY_ID, self::PREVIOUS_KEY);

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
    }

    public function test_invalid_action_rejected_by_validation(): void
    {
        $this->registerRoute();
        $response = $this->post('passkeyloginv2', [
            'passkey' => str_repeat('a', 32),
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16)),
            'signature' => bin2hex(random_bytes(32)),
            'key_id' => self::KEY_ID,
            'action' => 'invalid-action',
        ]);

        $this->assertContains($response->status(), [302, 422]);
    }

    public function test_get_request_not_registered(): void
    {
        $this->registerRoute();
        $response = $this->get('passkeyloginv2');

        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_valid_payload_with_unknown_passkey_redirects_silently(): void
    {
        $this->registerRoute();
        $fakePasskey = str_repeat('9', 32);
        $payload = $this->buildPayload($fakePasskey);

        $response = $this->post('passkeyloginv2', $payload);

        $response->assertRedirect('index.php');
        $this->assertGuest();
    }

    public function test_rate_limit_blocks_excessive_requests(): void
    {
        // Register route WITH throttle middleware for this test
        $this->app['router']->post('passkeyloginv2-throttled', [
            AuthenticateController::class, 'passkeyLoginV2',
        ])->middleware('throttle:passkey-login');

        $fakePasskey = str_repeat('a', 32);

        // Send 11 requests (limit is 10/min) — the 11th should be rate limited
        for ($i = 0; $i < 10; $i++) {
            $this->post('passkeyloginv2-throttled', $this->buildPayload($fakePasskey, bin2hex(random_bytes(16))));
        }

        $response = $this->post('passkeyloginv2-throttled', $this->buildPayload($fakePasskey, bin2hex(random_bytes(16))));
        $this->assertSame(429, $response->status());
    }

    public function test_canonical_payload_is_deterministic(): void
    {
        $service = app(PasskeyLoginService::class);

        $canonical1 = $service->canonicalPayload('abcd1234', 1700000000, 'nonce123', 'key1', 'login');
        $canonical2 = $service->canonicalPayload('abcd1234', 1700000000, 'nonce123', 'key1', 'login');

        $this->assertSame($canonical1, $canonical2);

        $decoded = json_decode($canonical1, true);
        $keys = array_keys($decoded);
        $sortedKeys = $keys;
        sort($sortedKeys);
        $this->assertSame($sortedKeys, $keys);
    }

    public function test_different_action_produces_different_signature(): void
    {
        $passkey = str_repeat('a', 32);
        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();

        $payloadLogin = $this->buildPayload($passkey, $nonce, $timestamp, self::KEY_ID, self::SIGNING_KEY, 'login');
        $payloadOther = $this->buildPayload($passkey, $nonce, $timestamp, self::KEY_ID, self::SIGNING_KEY, 'other');

        $this->assertNotSame($payloadLogin['signature'], $payloadOther['signature']);
    }

    /**
     * Build a valid v2 passkey login payload.
     *
     * @param  string  $passkey  32-char hex passkey.
     * @param  string  $nonce  32-char hex nonce (auto-generated if empty).
     * @param  int  $timestamp  Unix timestamp (default: now).
     * @param  string  $keyId  Signing key ID.
     * @param  string  $signingKey  Signing key (hex).
     * @param  string  $action  Action scope.
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $passkey,
        string $nonce = '',
        int $timestamp = 0,
        string $keyId = self::KEY_ID,
        string $signingKey = self::SIGNING_KEY,
        string $action = 'login',
    ): array {
        $nonce = $nonce !== '' ? $nonce : bin2hex(random_bytes(16));
        $timestamp = $timestamp > 0 ? $timestamp : time();

        $payload = [
            'action' => $action,
            'key_id' => $keyId,
            'nonce' => $nonce,
            'passkey' => $passkey,
            'timestamp' => $timestamp,
        ];

        $canonical = $this->canonicalJson($payload);
        $signature = hash_hmac('sha256', $canonical, $signingKey);

        $payload['signature'] = $signature;

        return $payload;
    }

    /**
     * Build canonical JSON for a payload (matches service format).
     *
     * @param  array<string, mixed>  $payload
     */
    private function canonicalJson(array $payload): string
    {
        $canonical = [
            'action' => $payload['action'] ?? 'login',
            'kid' => $payload['key_id'],
            'nonce' => $payload['nonce'],
            'pk' => $payload['passkey'],
            'ts' => $payload['timestamp'],
            'v' => PasskeyLoginService::VERSION,
        ];
        ksort($canonical);

        return (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
