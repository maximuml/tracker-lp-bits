<?php

namespace Tests;

use App\Models\User;
use App\Support\AuthCookie;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
