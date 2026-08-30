<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Auth\RecoveryController;
use App\Services\PasswordRecoveryService;
use App\Services\WebAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Mockery;
use Tests\TestCase;

final class RecoveryControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_recover_redirects_when_already_authenticated(): void
    {
        /** @var PasswordRecoveryService&Mockery\MockInterface $recoveryService */
        $recoveryService = Mockery::mock(PasswordRecoveryService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);
        Redirect::shouldReceive('to')->with('index.php')->once()->andReturn(
            new RedirectResponse('index.php')
        );

        $controller = new RecoveryController($recoveryService, $authService);
        $request = Request::create('/recover', 'GET');

        $response = $controller->recover($request);

        $this->assertTrue($response->isRedirect());
    }

    public function test_recover_post_requests_reset_and_redirects(): void
    {
        /** @var PasswordRecoveryService&Mockery\MockInterface $recoveryService */
        $recoveryService = Mockery::mock(PasswordRecoveryService::class);
        $recoveryService->shouldReceive('requestReset')->once();

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        Redirect::shouldReceive('to')->with('/recover?status=requested')->once()->andReturn(
            new RedirectResponse('/recover?status=requested')
        );

        $controller = new RecoveryController($recoveryService, $authService);
        $request = Request::create('/recover', 'POST', ['email' => 'test@test.com']);

        $response = $controller->recover($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('status=requested', $response->getTargetUrl());
    }

    public function test_recover_post_redirects_back_on_exception(): void
    {
        /** @var PasswordRecoveryService&Mockery\MockInterface $recoveryService */
        $recoveryService = Mockery::mock(PasswordRecoveryService::class);
        $recoveryService->shouldReceive('requestReset')
            ->once()
            ->andThrow(new AuthenticationException('Email not found.'));

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new RecoveryController($recoveryService, $authService);
        $request = Request::create('/recover', 'POST', ['email' => 'test@test.com']);

        $response = $controller->recover($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Email not found.', $response->getSession()->get('error'));
    }

    public function test_recover_get_with_id_and_secret_resets_password(): void
    {
        /** @var PasswordRecoveryService&Mockery\MockInterface $recoveryService */
        $recoveryService = Mockery::mock(PasswordRecoveryService::class);
        $recoveryService->shouldReceive('resetPassword')->once();

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        Redirect::shouldReceive('to')->with('/login?status=reset')->once()->andReturn(
            new RedirectResponse('/login?status=reset')
        );

        $controller = new RecoveryController($recoveryService, $authService);
        $request = Request::create('/recover', 'GET', ['id' => 1, 'secret' => 'abc123']);

        $response = $controller->recover($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('status=reset', $response->getTargetUrl());
    }

    public function test_recover_get_with_id_and_secret_redirects_back_on_exception(): void
    {
        /** @var PasswordRecoveryService&Mockery\MockInterface $recoveryService */
        $recoveryService = Mockery::mock(PasswordRecoveryService::class);
        $recoveryService->shouldReceive('resetPassword')
            ->once()
            ->andThrow(new AuthenticationException('Invalid reset link.'));

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new RecoveryController($recoveryService, $authService);
        $request = Request::create('/recover', 'GET', ['id' => 1, 'secret' => 'bad']);

        $response = $controller->recover($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Invalid reset link.', $response->getSession()->get('error'));
    }
}
