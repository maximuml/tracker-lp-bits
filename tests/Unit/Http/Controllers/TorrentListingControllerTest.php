<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentListingController;
use App\Models\User;
use App\Repositories\TorrentSearchRepository;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Tests\TestCase;

final class TorrentListingControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_redirects_to_login_when_not_authenticated(): void
    {
        $controller = app(TorrentListingController::class);
        $request = Request::create('/torrents', 'GET');

        $response = $controller->index($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login.php', $response->getTargetUrl());
    }

    public function test_index_returns_view_when_authenticated(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        Auth::guard('nexus-web')->setUser($user);

        $currentUser = app(CurrentUser::class);
        $currentUser->set($user->toLegacyArray());

        $repo = \Mockery::mock(TorrentSearchRepository::class);
        $repo->shouldReceive('getListingData')->andReturn(['torrents' => [], 'pageTitle' => 'Torrents']);
        app()->instance(TorrentSearchRepository::class, $repo);

        $controller = app(TorrentListingController::class);
        $request = Request::create('/torrents', 'GET');

        $response = $controller->index($request);

        $this->assertInstanceOf(View::class, $response);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
