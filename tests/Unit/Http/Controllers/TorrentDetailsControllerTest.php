<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentDetailsController;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TorrentDetailsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_show_aborts_for_zero_id(): void
    {
        $this->bindRepositories();

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request, 0);
    }

    public function test_show_aborts_for_negative_id(): void
    {
        $this->bindRepositories();

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', ['id' => -1]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->show($request, -1);
    }

    public function test_show_redirects_guest_to_login(): void
    {
        $this->bindRepositories();
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', ['id' => 5]);
        app()->instance('request', $request);

        $response = $controller->show($request, 5);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login.php', $response->getTargetUrl());
        $this->assertStringContainsString('returnto=', $response->getTargetUrl());
    }

    public function test_show_redirects_guest_to_login_with_full_url(): void
    {
        $this->bindRepositories();
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentDetailsController::class);
        $request = Request::create('/details', 'GET', ['id' => 10, 'hit' => 1]);
        app()->instance('request', $request);

        $response = $controller->show($request, 10);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login.php', $response->getTargetUrl());
        $this->assertStringContainsString('returnto=', $response->getTargetUrl());
    }

    /**
     * Bind mock repositories so the controller can be resolved from the container.
     */
    private function bindRepositories(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        app()->instance(TorrentRepository::class, $torrentRepository);

        /** @var SearchBoxRepository&Mockery\MockInterface $searchBoxRepository */
        $searchBoxRepository = Mockery::mock(SearchBoxRepository::class);
        app()->instance(SearchBoxRepository::class, $searchBoxRepository);

        /** @var TagRepository&Mockery\MockInterface $tagRepository */
        $tagRepository = Mockery::mock(TagRepository::class);
        app()->instance(TagRepository::class, $tagRepository);
    }
}
