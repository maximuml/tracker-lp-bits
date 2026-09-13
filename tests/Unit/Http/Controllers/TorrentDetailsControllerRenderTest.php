<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentDetailsController;
use App\Models\Torrent;
use App\Models\User;
use App\Support\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentDetailsControllerRenderTest extends TestCase
{
    protected function tearDown(): void
    {
        app(CurrentUser::class)->set(null);
        parent::tearDown();
    }

    public function test_show_renders_details_view_for_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['owner' => $user->id]);
        Auth::guard('nexus-web')->setUser($user);

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', ['id' => $torrent->id]);
        app()->instance('request', $request);

        $response = $controller->show($request, $torrent->id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString((string) $torrent->id, $response->getContent());
    }

    public function test_show_renders_details_view_with_request_flags(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['owner' => $user->id]);
        Auth::guard('nexus-web')->setUser($user);

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', [
            'id' => $torrent->id,
            'hit' => 1,
            'edited' => 1,
            'returnto' => '/torrents.php',
            'dllist' => 1,
        ]);
        app()->instance('request', $request);

        $response = $controller->show($request, $torrent->id);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }
}
