<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\AdminToolsController;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class AdminToolsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupMinimalLang();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_ban_log_redirects_guest_to_user_ban_log_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/user-ban-log', 'GET');
        app()->instance('request', $request);

        $response = $controller->userBanLog($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/user-ban-log.php', $response->getTargetUrl());
    }

    public function test_user_ban_log_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/user-ban-log', 'GET', ['q' => 'spam']);
        app()->instance('request', $request);

        $response = $controller->userBanLog($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/user-ban-log.php?q=spam', $response->getTargetUrl());
    }

    public function test_clear_cache_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/clearcache', 'GET');
        app()->instance('request', $request);

        $response = $controller->clearCache($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_testip_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/testip', 'GET');
        app()->instance('request', $request);

        $response = $controller->testip($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_location_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(AdminToolsController::class);
        $request = Request::create('/location', 'GET');
        app()->instance('request', $request);

        $response = $controller->location($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }

    /**
     * Set up minimal language strings so legacyAbortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void
    {
        app(Globals::class)->set('lang_functions', [
            'text_login' => 'Login',
            'text_signup' => 'Signup',
        ]);
    }
}
