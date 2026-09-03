<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\NexusException;
use App\Http\Controllers\TorrentDownloadController;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TorrentDownloadControllerTest extends TestCase
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

    public function test_download_aborts_for_zero_id_without_passkey_or_downhash(): void
    {
        $this->bindTorrentRepository();

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/download', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $this->expectException(NotFoundHttpException::class);

        $controller->download($request, app(TorrentRepository::class));
    }

    public function test_download_redirects_guest_to_login(): void
    {
        $this->bindTorrentRepository();

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/download', 'GET', ['id' => 5]);
        app()->instance('request', $request);

        $response = $controller->download($request, app(TorrentRepository::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/login.php', $response->getTargetUrl());
        $this->assertStringContainsString('returnto=', $response->getTargetUrl());
    }

    public function test_download_throws_for_invalid_downhash_format_without_dot(): void
    {
        $this->bindTorrentRepository();

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/download', 'GET', ['downhash' => 'invalid']);
        app()->instance('request', $request);

        $this->expectException(NexusException::class);
        $this->expectExceptionMessage('download.invalid_downhash_format');

        $controller->download($request, app(TorrentRepository::class));
    }

    public function test_download_throws_for_downhash_with_empty_second_part(): void
    {
        $this->bindTorrentRepository();

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/download', 'GET', ['downhash' => '123.']);
        app()->instance('request', $request);

        $this->expectException(NexusException::class);
        $this->expectExceptionMessage('download.invalid_downhash_format');

        $controller->download($request, app(TorrentRepository::class));
    }

    public function test_download_throws_for_downhash_with_empty_first_part(): void
    {
        $this->bindTorrentRepository();

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/download', 'GET', ['downhash' => '.abc']);
        app()->instance('request', $request);

        $this->expectException(NexusException::class);
        $this->expectExceptionMessage('download.invalid_downhash_format');

        $controller->download($request, app(TorrentRepository::class));
    }

    public function test_downloadnotice_redirects_guest_to_downloadnotice_php(): void
    {
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/downloadnotice', 'GET', ['torrentid' => 5, 'type' => 'client']);
        app()->instance('request', $request);

        $response = $controller->downloadnotice($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/downloadnotice.php', $response->getTargetUrl());
    }

    public function test_downloadnotice_post_returns_error_for_missing_torrentid(): void
    {
        $this->mockCurrentUser(['id' => 1, 'class' => 1]);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/downloadnotice', 'POST', [
            'id' => 0,
            'type' => 'client',
        ]);
        app()->instance('request', $request);

        $response = $controller->downloadnotice($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('error', (string) $response->getContent());
    }

    public function test_downloadnotice_post_returns_error_for_invalid_type(): void
    {
        $this->mockCurrentUser(['id' => 1, 'class' => 1]);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentDownloadController::class);
        $request = Request::create('/downloadnotice', 'POST', [
            'id' => 5,
            'type' => 'invalid',
        ]);
        app()->instance('request', $request);

        $response = $controller->downloadnotice($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('error', (string) $response->getContent());
    }

    /**
     * Bind a mock TorrentRepository so it can be resolved from the container.
     */
    private function bindTorrentRepository(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $torrentRepository */
        $torrentRepository = Mockery::mock(TorrentRepository::class);
        app()->instance(TorrentRepository::class, $torrentRepository);
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
