<?php

namespace Tests;

use App\Models\User;
use App\Support\AuthCookie;
use App\Support\DestructiveEnvironmentGuard;
use App\Support\LegacyRuntime;
use App\Support\Permissions;
use App\Support\Settings;
use App\Support\UserDisplay;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Fail fast before any test code runs: refuse to operate on a
        // database or Redis namespace that is not explicitly marked as a
        // test environment. This prevents Feature/E2E tests from
        // accidentally truncating tables in the dev/production database.
        // Called after parent::setUp() so that the Laravel container is
        // bootstrapped and config() is available.
        DestructiveEnvironmentGuard::assertTestingEnvironment();

        // ADR 0017: Feature/E2E suites run with NEXUS_LEGACY_CONTEXT=1 and
        // must see the same legacy-context code paths as production web
        // requests. bootEntry() sets the entry default so the flag survives
        // reset() inside the test (e.g. job lifecycle listeners).
        if (getenv('NEXUS_LEGACY_CONTEXT') === '1') {
            $this->app->make(LegacyRuntime::class)->bootEntry(true);
        }

        // Reset the static settings cache between tests so that changes
        // made by one test (and rolled back via DatabaseTransactions) don't
        // leak stale values into the next test in the same process.
        Settings::resetCache();

        // Same for per-process static caches: rolled-back rows reuse ids,
        // so a cached user row or permission verdict from a previous test
        // must not leak into the next one.
        Permissions::resetState();
        UserDisplay::resetState();
    }

    /**
     * Authenticate a test request as the given user via the legacy
     * `c_secure_pass` cookie. The value must not be encrypted by Laravel's
     * cookie middleware because `EncryptCookies` skips this cookie.
     */
    protected function withNexusCookie(User $user): self
    {
        $token = AuthCookie::buildToken($user->id, null, time() + 3600);

        return $this->withUnencryptedCookie('c_secure_pass', $token);
    }
}
