<?php

declare(strict_types=1);

namespace App\Services\Announce;

final readonly class InitialResponseResult
{
    public function __construct(
        public array $response,
        public int $realAnnounceInterval,
        public int $autocleanIntervalOne,
    ) {}
}
