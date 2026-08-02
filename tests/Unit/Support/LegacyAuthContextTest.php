<?php

namespace Tests\Unit\Support;

use App\Support\LegacyAuthContext;
use PHPUnit\Framework\TestCase;

class LegacyAuthContextTest extends TestCase
{
    public function test_holds_request_context_values(): void
    {
        $context = new LegacyAuthContext(
            user: ['id' => 42, 'class' => 4],
            lang: ['std_login_failed' => 'Login failed'],
            cache: null,
            ip: '127.0.0.1',
            requestUri: '/foo.php',
            request: ['action' => 'login'],
            cookies: ['c_secure_pass' => 'token'],
            maxLoginAttempts: 6,
            captchaEnabled: true,
            registration: ['invitesystem' => 'yes', 'maxusers' => 10000],
            langId: 2,
            moderatorClass: 4,
            script: 'ajax',
        );

        $this->assertSame(42, $context->user['id']);
        $this->assertSame('127.0.0.1', $context->ip);
        $this->assertSame(['action' => 'login'], $context->request);
        $this->assertTrue($context->isLoggedIn());
        $this->assertTrue($context->isModerator());
    }

    public function test_guest_user_is_not_logged_in(): void
    {
        $context = new LegacyAuthContext(
            user: null,
            lang: [],
            cache: null,
            ip: '127.0.0.1',
            requestUri: null,
            request: [],
            cookies: [],
            maxLoginAttempts: 0,
            captchaEnabled: false,
            registration: [],
            langId: null,
            moderatorClass: 4,
            script: 'login',
        );

        $this->assertFalse($context->isLoggedIn());
        $this->assertFalse($context->isModerator());
    }

    public function test_moderator_check_uses_moderator_class(): void
    {
        $context = new LegacyAuthContext(
            user: ['id' => 1, 'class' => 3],
            lang: [],
            cache: null,
            ip: '127.0.0.1',
            requestUri: null,
            request: [],
            cookies: [],
            maxLoginAttempts: 0,
            captchaEnabled: false,
            registration: [],
            langId: null,
            moderatorClass: 4,
            script: 'login',
        );

        $this->assertFalse($context->isModerator());
        $this->assertSame(3, $context->userClass());
    }
}
