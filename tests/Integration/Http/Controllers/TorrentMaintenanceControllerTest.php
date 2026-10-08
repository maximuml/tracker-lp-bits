<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\TorrentMaintenanceController;
use App\Http\Requests\FlushTorrentRequest;
use App\Http\Requests\ReseedTorrentRequest;
use App\Support\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
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
        $request = FlushTorrentRequest::create('/web/torrents/flush', 'POST', ['id' => 0]);
        app()->instance('request', $request);

        $response = $controller->flush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID.', (string) $response->getContent());
    }

    public function test_take_flush_returns_error_for_negative_id(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = FlushTorrentRequest::create('/web/torrents/flush', 'POST', ['id' => -5]);
        app()->instance('request', $request);

        $response = $controller->flush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid ID.', (string) $response->getContent());
    }

    public function test_take_flush_denies_flushing_other_users_without_permission(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = FlushTorrentRequest::create('/web/torrents/flush', 'POST', ['id' => 2]);
        app()->instance('request', $request);

        $response = $controller->flush($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('only clean your own ghost torrents', (string) $response->getContent());
    }

    public function test_take_flush_legacy_uri_redirects_to_rest_endpoint(): void
    {
        $controller = app(LegacyRedirectController::class);
        $request = Request::create('/takeflush', 'POST', ['id' => 2]);

        $response = $controller->post($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/torrents/flush', $response->getTargetUrl());
    }

    public function test_take_reseed_legacy_uri_redirects_to_rest_endpoint(): void
    {
        $controller = app(LegacyRedirectController::class);
        $request = Request::create('/takereseed', 'POST', ['reseedid' => 10]);

        $response = $controller->post($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/torrents/reseed', $response->getTargetUrl());
    }

    public function test_take_reseed_redirects_guest_to_takereseed_php(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(TorrentMaintenanceController::class);
        $request = ReseedTorrentRequest::create('/web/torrents/reseed?reseedid=10', 'POST');
        app()->instance('request', $request);

        $response = $controller->reseed($request);

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
        $real->set($user);
        app()->instance(CurrentUser::class, $real);
    }

    /**
     * Set up minimal language strings so abortResponse's stdhead()
     * can render for guest users (no authenticated user block).
     */
    private function setupMinimalLang(): void {}
}
