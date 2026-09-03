<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentMaintenanceController;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TorrentMaintenanceControllerTest extends TestCase
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

    public function test_take_flush_returns_error_for_invalid_id(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/takeflush', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $response = $controller->takeFlush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID.', (string) $response->getContent());
    }

    public function test_take_flush_returns_error_for_negative_id(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/takeflush', 'GET', ['id' => -5]);
        app()->instance('request', $request);

        $response = $controller->takeFlush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID.', (string) $response->getContent());
    }

    public function test_take_flush_denies_flushing_other_users_without_permission(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/takeflush', 'GET', ['id' => 2]);
        app()->instance('request', $request);

        $response = $controller->takeFlush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('cannot flush other users', (string) $response->getContent());
    }

    public function test_take_reseed_redirects_guest_to_takereseed_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/takereseed', 'GET', ['reseedid' => 10]);
        app()->instance('request', $request);

        $response = $controller->takeReseed($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/takereseed.php', $response->getTargetUrl());
        $this->assertStringContainsString('reseedid=10', $response->getTargetUrl());
    }

    public function test_torrent_info_aborts_for_invalid_id(): void
    {
        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/torrent_info', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->torrentInfo($request);
    }

    public function test_torrent_info_aborts_for_negative_id(): void
    {
        $controller = app(TorrentMaintenanceController::class);
        $request = Request::create('/torrent_info', 'GET', ['id' => -1]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->torrentInfo($request);
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
