<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Http\Controllers\CompatRedirectController;
use App\Http\Controllers\TorrentDeleteController;
use App\Http\Requests\DeleteTorrentRequest;
use App\Http\Requests\FastDeleteTorrentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
final class TorrentDeleteControllerTest extends TestCase
{
    use MakesCurrentUser;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fast_delete_legacy_uri_redirects_to_rest_endpoint(): void
    {
        $controller = App::make(CompatRedirectController::class);
        $request = Request::create('/fastdelete', 'POST', ['id' => 1]);

        $response = $controller->post($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/torrents/fast-delete', $response->getTargetUrl());
    }

    public function test_fast_delete_redirects_when_not_authenticated(): void
    {
        app()->instance(ActorContext::class, $this->actor([]));

        $controller = App::make(TorrentDeleteController::class);
        $request = FastDeleteTorrentRequest::create('/web/torrents/fast-delete?id=1', 'POST');

        $response = $controller->fastDeleteTorrent($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/fastdelete', $response->getTargetUrl());
    }

    public function test_delete_legacy_uri_redirects_to_rest_endpoint(): void
    {
        $controller = App::make(CompatRedirectController::class);
        $request = Request::create('/delete', 'POST', ['id' => 1]);

        $response = $controller->post($request);

        $this->assertTrue($response->isRedirect());
        $this->assertSame(308, $response->getStatusCode());
        $this->assertStringContainsString('/web/torrents/delete', $response->getTargetUrl());
    }

    public function test_delete_redirects_when_not_authenticated(): void
    {
        app()->instance(ActorContext::class, $this->actor([]));

        $controller = App::make(TorrentDeleteController::class);
        $request = DeleteTorrentRequest::create('/web/torrents/delete?id=1', 'POST');

        $response = $controller->deleteTorrent($request);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/delete', $response->getTargetUrl());
    }
}
