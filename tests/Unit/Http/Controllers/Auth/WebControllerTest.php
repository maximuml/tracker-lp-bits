<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Auth;

use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Auth\WebController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\WebAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

final class WebControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_redirects_when_already_authenticated(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);
        Redirect::shouldReceive('intended')->with('index.php')->once()->andReturn(
            new RedirectResponse('index.php')
        );

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
    }

    public function test_login_redirects_back_when_banned(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')
            ->once()
            ->andThrow(new AuthenticationException('You are banned.'));

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('You are banned.', $response->getSession()->get('error'));
    }

    public function test_login_throws_validation_exception_on_empty_data(): void
    {
        $this->expectException(ValidationException::class);

        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', []);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $controller->login($request);
    }

    public function test_login_redirects_to_index_on_success(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->once();
        $authService->shouldReceive('authenticate')->once();

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('index.php', $response->getTargetUrl());
    }

    public function test_login_redirects_to_returnto_when_local_url(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->once();
        $authService->shouldReceive('authenticate')->once();

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
            'returnto' => '/torrents.php',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('torrents.php', $response->getTargetUrl());
    }

    public function test_login_ignores_external_returnto(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->once();
        $authService->shouldReceive('authenticate')->once();

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'password',
            'returnto' => 'https://evil.com/path',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('index.php', $response->getTargetUrl());
    }

    public function test_login_redirects_back_on_auth_exception(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('assertNotBanned')->once();
        $authService->shouldReceive('authenticate')
            ->once()
            ->andThrow(new AuthenticationException('Invalid credentials.'));

        $guard = Mockery::mock();
        $guard->shouldReceive('check')->once()->andReturn(false);
        Auth::shouldReceive('guard')->with('nexus-web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = LoginRequest::create('/login', 'POST', [
            'username' => 'testuser',
            'password' => 'wrongpass',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $response = $controller->login($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Invalid credentials.', $response->getSession()->get('error'));
    }

    public function test_logout_calls_service_and_redirects(): void
    {
        /** @var WebAuthService&Mockery\MockInterface $authService */
        $authService = Mockery::mock(WebAuthService::class);
        $authService->shouldReceive('logout')->once();

        $guard = Mockery::mock();
        $guard->shouldReceive('logout')->once();
        Auth::shouldReceive('guard')->with('web')->once()->andReturn($guard);

        $controller = new WebController($authService);
        $request = Request::create('/logout', 'GET');

        $response = $controller->logout($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }
}
