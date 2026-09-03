<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentRssController;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class TorrentRssControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_torrentrss_returns_error_when_passkey_missing(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET');
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_returns_error_when_passkey_empty_string(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(['passkey' => '']);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET', ['passkey' => '']);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_uses_passkey_from_current_user_when_not_in_request(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(['passkey' => '']);
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET');
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_returns_invalid_passkey_for_unknown_passkey(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->bind(LegacyRedisCache::class, fn () => null);
        Cache::flush();

        $controller = app(TorrentRssController::class);
        $fakePasskey = 'fake-'.bin2hex(random_bytes(8));
        $request = Request::create('/torrentrss', 'GET', ['passkey' => $fakePasskey]);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('invalid passkey', (string) $response->getContent());
    }

    /**
     * Bind a mock TorrentRepository so the controller can be resolved.
     */
    private function bindTorrentRepository(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class);
        app()->instance(TorrentRepository::class, $repository);
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
}
