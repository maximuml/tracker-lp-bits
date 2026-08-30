<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Requests\Auth\ConfirmResendRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\WebAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Mockery;
use Tests\TestCase;

final class RegistrationControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_signup_redirects_when_already_authenticated(): void
    {
        /** @var RegistrationService&Mockery\MockInterface $registrationService */
        $registrationService = Mockery::mock(RegistrationService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);
        Redirect::shouldReceive('to')->with('index.php')->once()->andReturn(
            new RedirectResponse('index.php')
        );

        $controller = new RegistrationController($registrationService, $authService);
        $request = SignupRequest::create('/signup', 'POST', [
            'wantusername' => 'newuser',
            'wantpassword' => 'password',
            'passagain' => 'password',
            'email' => 'test@test.com',
        ]);

        $response = $controller->signup($request);

        $this->assertTrue($response->isRedirect());
    }

    public function test_confirm_redirects_to_ok_on_success(): void
    {
        /** @var RegistrationService&Mockery\MockInterface $registrationService */
        $registrationService = Mockery::mock(RegistrationService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $user = new User;
        $user->status = 'confirmed';

        $registrationService->shouldReceive('confirm')
            ->once()
            ->andReturn($user);

        Redirect::shouldReceive('to')->with('ok.php?type=confirm')->once()->andReturn(
            new RedirectResponse('ok.php?type=confirm')
        );

        $controller = new RegistrationController($registrationService, $authService);
        $request = Request::create('/confirm', 'GET', ['id' => 1, 'secret' => 'abc']);

        $response = $controller->confirm($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('ok.php?type=confirm', $response->getTargetUrl());
    }

    public function test_confirm_redirects_to_confirmed_on_exception(): void
    {
        /** @var RegistrationService&Mockery\MockInterface $registrationService */
        $registrationService = Mockery::mock(RegistrationService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $registrationService->shouldReceive('confirm')
            ->once()
            ->andThrow(new AuthenticationException('Invalid secret.'));

        Redirect::shouldReceive('to')->with('ok.php?type=confirmed')->once()->andReturn(
            new RedirectResponse('ok.php?type=confirmed')
        );

        $controller = new RegistrationController($registrationService, $authService);
        $request = Request::create('/confirm', 'GET', ['id' => 1, 'secret' => 'bad']);

        $response = $controller->confirm($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('ok.php?type=confirmed', $response->getTargetUrl());
    }

    public function test_confirm_redirects_to_confirmed_for_non_pending_status(): void
    {
        /** @var RegistrationService&Mockery\MockInterface $registrationService */
        $registrationService = Mockery::mock(RegistrationService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $user = new User;
        $user->status = 'banned';

        $registrationService->shouldReceive('confirm')
            ->once()
            ->andReturn($user);

        Redirect::shouldReceive('to')->with('ok.php?type=confirmed')->once()->andReturn(
            new RedirectResponse('ok.php?type=confirmed')
        );

        $controller = new RegistrationController($registrationService, $authService);
        $request = Request::create('/confirm', 'GET', ['id' => 1, 'secret' => 'abc']);

        $response = $controller->confirm($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('ok.php?type=confirmed', $response->getTargetUrl());
    }

    public function test_resend_confirmation_redirects_when_already_authenticated(): void
    {
        /** @var RegistrationService&Mockery\MockInterface $registrationService */
        $registrationService = Mockery::mock(RegistrationService::class);
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);
        Redirect::shouldReceive('to')->with('index.php')->once()->andReturn(
            new RedirectResponse('index.php')
        );

        $controller = new RegistrationController($registrationService, $authService);
        $request = ConfirmResendRequest::create('/confirm_resend', 'POST', [
            'email' => 'test@test.com',
        ]);

        $response = $controller->resendConfirmation($request);

        $this->assertTrue($response->isRedirect());
    }
}
