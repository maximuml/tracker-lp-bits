<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\InfoController;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Tests\TestCase;

final class InfoControllerTest extends TestCase
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

    public function test_userhistory_redirects_guest_to_userhistory_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(InfoController::class);
        $request = Request::create('/userhistory', 'GET');
        app()->instance('request', $request);

        $response = $controller->userhistory($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/userhistory.php', $response->getTargetUrl());
    }

    public function test_userhistory_redirects_guest_preserving_query_string(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(InfoController::class);
        $request = Request::create('/userhistory', 'GET', ['id' => 5]);
        app()->instance('request', $request);

        $response = $controller->userhistory($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/userhistory.php?id=5', $response->getTargetUrl());
    }

    public function test_donated_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(InfoController::class);
        $request = Request::create('/donated', 'GET');
        app()->instance('request', $request);

        $response = $controller->donated($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_bitbucketlog_denies_access_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(InfoController::class);
        $request = Request::create('/bitbucketlog', 'GET');
        app()->instance('request', $request);

        $response = $controller->bitbucketlog($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Access denied', (string) $response->getContent());
    }

    public function test_bitbucketlog_denies_delete_via_get_request_for_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(InfoController::class);
        $request = Request::create('/bitbucketlog', 'GET', ['delete' => 1]);
        app()->instance('request', $request);

        $response = $controller->bitbucketlog($request);

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
