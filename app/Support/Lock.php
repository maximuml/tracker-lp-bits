<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\LockFailException;
use Illuminate\Contracts\Cache\Lock as LockContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class Lock
{
    private string $owner;

    private LockContract $lock;

    public function __construct(private string $name, int $seconds)
    {
        $this->owner = Str::random(32);
        $this->lock = Cache::lock($name, $seconds, $this->owner);
    }

    public function get(): bool
    {
        return (bool) $this->lock->get();
    }

    public function acquire(): bool
    {
        return (bool) $this->lock->get();
    }

    public function release(): bool
    {
        return (bool) $this->lock->release();
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public static function lockOrFail(string $name, int $seconds): self
    {
        $lock = new self($name, $seconds);
        if (! $lock->acquire()) {
            Logger::writeWithContext("{$name} failed to acquire lock", 'error');
            throw new LockFailException($name, $lock->owner());
        }

        return $lock;
    }
}
