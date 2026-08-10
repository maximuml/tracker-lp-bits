<?php

declare(strict_types=1);

namespace App\Services\Announce;

final readonly class TrafficResult
{
    public function __construct(
        public int $upthis,
        public int $downthis,
        public ?string $snatchTimeColumn,
        public int $snatchTimeIncrement,
        public int $leechTimeNoSeederIncrement,
        public int $uploadedIncrementForUser,
        public int $downloadedIncrementForUser,
    ) {}
}
