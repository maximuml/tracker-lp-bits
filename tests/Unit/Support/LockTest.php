<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Exceptions\LockFailException;
use App\Support\Lock;
use Tests\TestCase;

/**
 * Unit tests for Lock.
 *
 * Uses the real Redis cache backend (available in the test Docker stack).
 * Each test uses a unique lock name to avoid interference.
 */
final class LockTest extends TestCase
{
    private function uniqueName(): string
    {
        return 'test:lock:'.uniqid('', true);
    }

    public function test_get_acquires_free_lock(): void
    {
        $name = $this->uniqueName();
        $lock = new Lock($name, 5);

        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_get_returns_false_for_held_lock(): void
    {
        $name = $this->uniqueName();
        $first = new Lock($name, 5);
        $this->assertTrue($first->get());

        $second = new Lock($name, 5);
        $this->assertFalse($second->get());

        $first->release();
    }

    public function test_acquire_is_alias_of_get(): void
    {
        $name = $this->uniqueName();
        $lock = new Lock($name, 5);

        $this->assertTrue($lock->acquire());
        $lock->release();
    }

    public function test_release_returns_true_when_held(): void
    {
        $name = $this->uniqueName();
        $lock = new Lock($name, 5);
        $lock->get();

        $this->assertTrue($lock->release());
    }

    public function test_release_returns_false_when_not_held(): void
    {
        $name = $this->uniqueName();
        $lock = new Lock($name, 5);

        // Never acquired — release should return false
        $this->assertFalse($lock->release());
    }

    public function test_owner_returns_unique_string(): void
    {
        $lock1 = new Lock($this->uniqueName(), 5);
        $lock2 = new Lock($this->uniqueName(), 5);

        $this->assertSame(32, strlen($lock1->owner()));
        $this->assertNotSame($lock1->owner(), $lock2->owner());
    }

    public function test_lock_or_fail_returns_lock_when_free(): void
    {
        $name = $this->uniqueName();
        $lock = Lock::lockOrFail($name, 5);

        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertTrue($lock->release());
    }

    public function test_lock_or_fail_throws_when_held(): void
    {
        $name = $this->uniqueName();
        $first = Lock::lockOrFail($name, 5);

        $this->expectException(LockFailException::class);

        try {
            Lock::lockOrFail($name, 5);
        } finally {
            $first->release();
        }
    }

    public function test_lock_can_be_reacquired_after_release(): void
    {
        $name = $this->uniqueName();
        $lock1 = new Lock($name, 5);
        $this->assertTrue($lock1->get());
        $this->assertTrue($lock1->release());

        $lock2 = new Lock($name, 5);
        $this->assertTrue($lock2->get());
        $lock2->release();
    }
}
