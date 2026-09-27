<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RedisDegradedMode;
use App\Support\RedisGuard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class RedisDegradedModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RedisGuard::reset();
    }

    protected function tearDown(): void
    {
        RedisGuard::reset();
        parent::tearDown();
    }

    public function test_degrades_drivers_while_redis_flagged_down(): void
    {
        RedisGuard::markDown();

        $observed = [];
        $response = (new RedisDegradedMode)->handle(
            Request::create('/', 'GET'),
            static function () use (&$observed) {
                $observed = [
                    'session' => config('session.driver'),
                    'cache' => config('cache.default'),
                    'queue' => config('queue.default'),
                ];

                return new Response('ok');
            }
        );

        $this->assertSame(['session' => 'file', 'cache' => 'file', 'queue' => 'sync'], $observed);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_restores_drivers_after_request(): void
    {
        RedisGuard::markDown();
        $sessionBefore = config('session.driver');
        $cacheBefore = config('cache.default');
        $queueBefore = config('queue.default');

        (new RedisDegradedMode)->handle(
            Request::create('/', 'GET'),
            static fn () => new Response('ok')
        );

        $this->assertSame($sessionBefore, config('session.driver'));
        $this->assertSame($cacheBefore, config('cache.default'));
        $this->assertSame($queueBefore, config('queue.default'));
    }

    public function test_restores_drivers_even_when_handler_throws(): void
    {
        RedisGuard::markDown();
        $sessionBefore = config('session.driver');

        try {
            (new RedisDegradedMode)->handle(
                Request::create('/', 'GET'),
                static function (): never {
                    throw new \RuntimeException('boom');
                }
            );
            $this->fail('handler exception should propagate');
        } catch (\RuntimeException) {
        }

        $this->assertSame($sessionBefore, config('session.driver'));
    }

    public function test_does_not_swap_drivers_when_redis_is_up(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('ping')->once()->andReturn(true);
        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $sessionBefore = config('session.driver');
        $observed = null;
        (new RedisDegradedMode)->handle(
            Request::create('/', 'GET'),
            static function () use (&$observed) {
                $observed = config('session.driver');

                return new Response('ok');
            }
        );

        $this->assertSame($sessionBefore, $observed);
    }
}
