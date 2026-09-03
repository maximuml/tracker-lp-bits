<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UtilityController;
use App\Repositories\UserPasskeyRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class UtilityControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ajax_redirects_to_ajax_php_when_redis_cache_is_null(): void
    {
        app()->bind(LegacyRedisCache::class, fn () => null);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/ajax.php', $response->getTargetUrl());
    }

    public function test_ajax_returns_error_for_invalid_action(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'invalidAction']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(1, $data['ret']);
        $this->assertStringContainsString('Invalid action', $data['msg']);
    }

    public function test_ajax_returns_success_for_valid_action(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $mockPasskeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $mockPasskeyRepo->shouldReceive('getGetArgs')->once()->andReturn(['challenge' => 'test-challenge']);
        app()->instance(UserPasskeyRepository::class, $mockPasskeyRepo);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(0, $data['ret']);
        $this->assertSame(['challenge' => 'test-challenge'], $data['data']);
    }

    public function test_ajax_returns_error_for_exception(): void
    {
        $this->mockLegacyRedisCache();
        $this->mockCurrentUser(['id' => 1, 'enabled' => true, 'username' => 'testuser']);

        $mockPasskeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $mockPasskeyRepo->shouldReceive('getGetArgs')->once()->andThrow(new \RuntimeException('Test error'));
        app()->instance(UserPasskeyRepository::class, $mockPasskeyRepo);

        $controller = app(UtilityController::class);
        $request = Request::create('/ajax', 'GET', ['action' => 'getPasskeyGetArgs']);
        app()->instance('request', $request);

        $response = $controller->ajax($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertSame(-1, $data['ret']);
        $this->assertSame('Test error', $data['msg']);
    }

    /**
     * Bind a mock LegacyRedisCache so the controller's app() resolution
     * returns a non-null cache without connecting to Redis.
     */
    private function mockLegacyRedisCache(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        app()->instance(LegacyRedisCache::class, $cache);
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
