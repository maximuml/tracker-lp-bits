<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RedisGuard;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class RedisGuardTest extends TestCase
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

    public function test_attempt_returns_result_when_operation_succeeds(): void
    {
        $this->assertSame('ok', RedisGuard::attempt(static fn () => 'ok', 'fallback'));
        $this->assertTrue(RedisGuard::available());
    }

    public function test_attempt_returns_fallback_and_marks_down_on_redis_exception(): void
    {
        $result = RedisGuard::attempt(static function () {
            throw new \RedisException('connection refused');
        }, 'fallback');

        $this->assertSame('fallback', $result);
        $this->assertFalse(RedisGuard::available());
    }

    public function test_attempt_skips_operation_while_marked_down(): void
    {
        RedisGuard::markDown();

        $called = false;
        $result = RedisGuard::attempt(static function () use (&$called) {
            $called = true;

            return 'ok';
        }, 'fallback');

        $this->assertFalse($called);
        $this->assertSame('fallback', $result);
    }

    public function test_domain_exceptions_still_propagate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RedisGuard::attempt(static function () {
            throw new \InvalidArgumentException('not a redis error');
        });
    }

    public function test_reset_restores_availability(): void
    {
        RedisGuard::markDown();
        $this->assertFalse(RedisGuard::available());
        RedisGuard::reset();
        $this->assertTrue(RedisGuard::available());
    }

    public function test_expired_flag_allows_a_single_prober(): void
    {
        // Simulate a stale down-flag written in the past.
        @file_put_contents(storage_path('framework/cache/redis-down'), (string) (microtime(true) - 1));
        $this->assertTrue(RedisGuard::available(), 'expired flag should allow probing');
        // A second process-style check sees the probe window, not availability.
        RedisGuard::reset();
    }
}
