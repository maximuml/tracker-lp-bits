<?php

namespace Tests;

use App\Models\User;
use App\Support\AuthCookie;
use App\Support\DestructiveEnvironmentGuard;
use App\Support\Settings;
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

        // Reset the static settings cache between tests so that changes
        // made by one test (and rolled back via DatabaseTransactions) don't
        // leak stale values into the next test in the same process.
        Settings::resetCache();
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
