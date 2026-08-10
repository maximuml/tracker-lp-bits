<?php

declare(strict_types=1);

namespace App\Services\Announce;

/**
 * Result object returned by the announce rate limiter.
 */
final readonly class RateLimitResult
{
    public function __construct(public bool $isReAnnounce)
    {
    }
}
