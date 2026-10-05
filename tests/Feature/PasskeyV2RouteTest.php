<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasskeyLoginService;
use App\Support\Settings;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Real-route coverage for POST /auth/passkey and the legacy
 * passkey-login secret URI (SEC-03).
 *
 * The sibling PasskeyLoginV2Test exercises the protocol details on the
 * same route; this file covers what production actually serves — the
 * unconditional /auth/passkey route plus the passkey.v2 middleware, the
 * fallback dispatcher for the configured `login_secret` URI, and the
 * route-cache lifecycle. These are the paths that die silently when
 * routes are registered behind PHP_SAPI checks or when `route:cache`
 * freezes a DB-read feature flag.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class PasskeyV2RouteTest extends TestCase
{
    use DatabaseTransactions;

    private const HMAC_MATERIAL = 'a1b2c3d4e5f6a1b2';

    private const SIGNING_KEY = self::HMAC_MATERIAL.self::HMAC_MATERIAL.self::HMAC_MATERIAL.self::HMAC_MATERIAL;

    private const KEY_ID = 'test-key-1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Settings::resetCache();
        Settings::saveBatch('security', [
            'passkey_login_v2_enabled' => 'yes',
            'passkey_login_signing_key_current' => self::SIGNING_KEY,
            'passkey_login_signing_key_id_current' => self::KEY_ID,
            'login_secret' => 'test-secret-uri',
            'login_secret_deadline' => now()->addDays(30)->toDateTimeString(),
            'login_type' => 'passkey',
        ]);
        Settings::resetCache();

        // The real route carries throttle:passkey-login — clear the bucket
        // so consecutive tests in this file do not eat each other's quota.
        RateLimiter::clear(md5('passkey-login'.'127.0.0.1'));
    }

    public function test_real_route_is_registered_outside_console_gate(): void
    {
        // Under the previous implementation this assertion failed because
        // the route only registered when PHP_SAPI !== 'cli' — i.e. never
        // inside `route:cache` or the test suite.
        $route = Route::getRoutes()->match(Request::create('/auth/passkey', 'POST'));

        $this->assertSame('passkeyLoginV2', $route->getActionMethod());
    }

    public function test_real_route_rejects_when_feature_flag_off(): void
    {
        Settings::saveBatch('security', ['passkey_login_v2_enabled' => 'no']);
        Settings::resetCache();

        $user = User::factory()->create([
            'passkey' => str_repeat('a', 32),
            'status' => 1,
            'enabled' => true,
        ]);

        $response = $this->post('/auth/passkey', $this->buildPayload($user->passkey));

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_real_route_authenticates_and_sets_cookie(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('a', 32),
            'status' => 1,
            'enabled' => true,
        ]);

        $response = $this->post('/auth/passkey', $this->buildPayload($user->passkey));

        $response->assertRedirect('/web/index');
        // setcookie() does not reach the TestResponse — the observable
        // side effect of a successful login is last_login + a login log.
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_expired_deadline_rejects_with_404(): void
    {
        Settings::saveBatch('security', [
            'login_secret_deadline' => now()->subDay()->toDateTimeString(),
        ]);
        Settings::resetCache();

        $user = User::factory()->create([
            'passkey' => str_repeat('b', 32),
            'status' => 1,
            'enabled' => true,
        ]);

        $response = $this->post('/auth/passkey', $this->buildPayload($user->passkey));

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_disabled_user_is_not_logged_in(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('c', 32),
            'status' => 1,
            'enabled' => false,
        ]);

        $response = $this->post('/auth/passkey', $this->buildPayload($user->passkey));

        $response->assertRedirect('/web/index');
        $this->assertNull($user->fresh()->last_login);
    }

    public function test_unconfirmed_user_is_not_logged_in(): void
    {
        $user = User::factory()->create([
            'passkey' => str_repeat('d', 32),
            'status' => 0,
            'enabled' => true,
        ]);

        $response = $this->post('/auth/passkey', $this->buildPayload($user->passkey));

        $response->assertRedirect('/web/index');
        $this->assertNull($user->fresh()->last_login);
    }

    public function test_nonce_marker_outlives_signature_validity(): void
    {
        // Signature at the far (future) edge of the window stays valid
        // until ts + 300 s; the marker TTL must cover that whole span or
        // a replay after the fixed 300 s would succeed.
        $now = time();
        $futureEdge = $now + PasskeyLoginService::TIMESTAMP_WINDOW - 5;

        $ttl = PasskeyLoginService::nonceTtlSeconds($futureEdge, $now);

        // Remaining signature validity is ~305 s — marker must live longer
        // than the signature, never less.
        $this->assertGreaterThanOrEqual(
            $futureEdge + PasskeyLoginService::TIMESTAMP_WINDOW - $now,
            $ttl,
        );
        $this->assertSame(
            PasskeyLoginService::TIMESTAMP_WINDOW * 2 - 5 + PasskeyLoginService::NONCE_TTL_GRACE,
            $ttl,
        );

        // Past edge: signature nearly expired, TTL shrinks accordingly.
        $pastEdge = $now - PasskeyLoginService::TIMESTAMP_WINDOW + 5;
        $this->assertSame(5 + PasskeyLoginService::NONCE_TTL_GRACE, PasskeyLoginService::nonceTtlSeconds($pastEdge, $now));

        // Exact boundary never produces a non-positive TTL.
        $this->assertSame(1, PasskeyLoginService::nonceTtlSeconds($now - PasskeyLoginService::TIMESTAMP_WINDOW - 60, $now));
    }

    public function test_nonce_store_failure_rejects_login(): void
    {
        $repository = \Mockery::mock(Repository::class);
        $repository->shouldReceive('add')->once()->andThrow(new \RuntimeException('store down'));
        $this->app->instance(Repository::class, $repository);

        $service = $this->app->make(PasskeyLoginService::class);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        // A fully valid signature so the flow reaches the nonce store —
        // the store failure itself must produce the denial.
        $canonical = $service->canonicalPayload(str_repeat('e', 32), $timestamp, $nonce, self::KEY_ID, 'login');

        // verify() must return false — fail closed, never proceed without
        // replay protection. The exception is swallowed into a denial.
        $this->assertFalse(
            $service->verify(
                str_repeat('e', 32),
                $timestamp,
                $nonce,
                hash_hmac('sha256', $canonical, self::SIGNING_KEY),
                self::KEY_ID,
            ),
        );
    }

    public function test_unsupported_action_scope_rejected_by_service(): void
    {
        $service = $this->app->make(PasskeyLoginService::class);

        // Signature is perfectly valid — only the action scope is wrong.
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $service->canonicalPayload(str_repeat('f', 32), $timestamp, $nonce, self::KEY_ID, 'admin');

        $this->assertFalse(
            $service->verify(
                str_repeat('f', 32),
                $timestamp,
                $nonce,
                hash_hmac('sha256', $canonical, self::SIGNING_KEY),
                self::KEY_ID,
                'admin',
            ),
        );
    }

    public function test_previous_key_rejected_after_deadline(): void
    {
        Settings::saveBatch('security', [
            'passkey_login_signing_key_previous' => str_repeat('9', 64),
            'passkey_login_signing_key_id_previous' => 'old-key',
            'passkey_login_previous_key_deadline' => now()->subMinute()->toDateTimeString(),
        ]);
        Settings::resetCache();

        $service = $this->app->make(PasskeyLoginService::class);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $service->canonicalPayload(str_repeat('a', 32), $timestamp, $nonce, 'old-key', 'login');

        $this->assertFalse(
            $service->verify(
                str_repeat('a', 32),
                $timestamp,
                $nonce,
                hash_hmac('sha256', $canonical, str_repeat('9', 64)),
                'old-key',
            ),
        );
    }

    public function test_previous_key_rejected_without_deadline(): void
    {
        // A configured previous key with no deadline must NOT be accepted —
        // otherwise the rotation overlap silently stays open-ended.
        Settings::saveBatch('security', [
            'passkey_login_signing_key_previous' => str_repeat('9', 64),
            'passkey_login_signing_key_id_previous' => 'old-key',
        ]);
        Settings::resetCache();

        $service = $this->app->make(PasskeyLoginService::class);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $service->canonicalPayload(str_repeat('a', 32), $timestamp, $nonce, 'old-key', 'login');

        $this->assertFalse(
            $service->verify(
                str_repeat('a', 32),
                $timestamp,
                $nonce,
                hash_hmac('sha256', $canonical, str_repeat('9', 64)),
                'old-key',
            ),
        );
    }

    public function test_legacy_secret_uri_dispatches_via_fallback(): void
    {
        // POST to the configured `login_secret` URI reaches the real
        // passkeyLogin action through the fallback route — no static
        // route registration required, so route:cache cannot lose it.
        $user = User::factory()->create([
            'passkey' => str_repeat('a', 32),
            'status' => 1,
            'enabled' => true,
        ]);

        $timestamp = time();
        $signature = hash_hmac('sha256', $user->passkey.$timestamp, 'test-secret-uri');

        $response = $this->post('/test-secret-uri', [
            'passkey' => $user->passkey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);

        $response->assertRedirect('/web/index');
        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_fallback_returns_404_for_unrelated_paths(): void
    {
        $response = $this->post('/definitely-not-the-secret', ['passkey' => str_repeat('a', 32)]);

        $response->assertNotFound();
    }

    public function test_fallback_get_to_secret_uri_is_404(): void
    {
        $response = $this->get('/test-secret-uri');

        // The GET catch-all answers unrouted GETs with 404 so the POST
        // passkey catch-all no longer turns missing pages into 405s.
        $response->assertNotFound();
    }

    public function test_get_to_missing_page_is_404_not_405(): void
    {
        $response = $this->get('/definitely-not-a-real-page-'.bin2hex(random_bytes(4)));

        $response->assertNotFound();
    }

    public function test_get_to_auth_passkey_is_not_allowed(): void
    {
        $response = $this->get('/auth/passkey');

        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_route_cache_keeps_auth_passkey_route(): void
    {
        // Builds the real production route cache in a subprocess and
        // verifies /auth/passkey survives — the original bug was that
        // `!Environment::isConsole()` skipped registration during
        // `artisan route:cache`, silently dropping the endpoint from the
        // cached table. try/finally clears the cache file so the shared
        // dev bootstrap/cache is restored.
        $cachedRoutesPath = $this->app->getCachedRoutesPath();

        try {
            $exitCode = Artisan::call('route:cache');
            $this->assertSame(0, $exitCode);
            $this->assertFileExists($cachedRoutesPath);

            $compiled = (string) file_get_contents($cachedRoutesPath);
            $this->assertStringContainsString('auth/passkey', $compiled);
            $this->assertStringContainsString('passkeyLoginV2', $compiled);
        } finally {
            Artisan::call('route:clear');
        }

        $this->assertFileDoesNotExist($cachedRoutesPath);
    }

    /**
     * Build a valid v2 passkey login payload.
     *
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

        $canonical = [
            'action' => $action,
            'kid' => $keyId,
            'nonce' => $nonce,
            'pk' => $passkey,
            'ts' => $timestamp,
            'v' => PasskeyLoginService::VERSION,
        ];
        ksort($canonical);

        $payload['signature'] = hash_hmac(
            'sha256',
            (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $signingKey,
        );

        return $payload;
    }
}
