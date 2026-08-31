<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentBookmarkController;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TorrentBookmarkControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bookmark_returns_failed_for_guest(): void
    {
        $controller = app(TorrentBookmarkController::class);
        $request = Request::create('/bookmark.php', 'GET', ['torrentid' => 1]);
        app()->instance('request', $request);

        $response = $controller->bookmark($request);

        $this->assertSame('failed', $response->getContent());
        $this->assertSame('text/xml; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_bookmark_returns_failed_for_invalid_torrent_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $controller = app(TorrentBookmarkController::class);
        $request = Request::create('/bookmark.php', 'GET', ['torrentid' => 0]);
        app()->instance('request', $request);

        $response = $controller->bookmark($request);

        $this->assertSame('failed', $response->getContent());
    }

    public function test_bookmark_toggles_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $torrent = Torrent::factory()->create();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $controller = app(TorrentBookmarkController::class);
        $request = Request::create('/bookmark.php', 'GET', ['torrentid' => $torrent->id]);
        app()->instance('request', $request);

        $response = $controller->bookmark($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('failed', $response->getContent());
    }

    public function test_thanks_redirects_guest_to_login(): void
    {
        $controller = app(TorrentBookmarkController::class);
        $request = Request::create('/thanks.php', 'GET');
        app()->instance('request', $request);

        $response = $controller->thanks($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/thanks.php', $response->getTargetUrl());
    }
}
