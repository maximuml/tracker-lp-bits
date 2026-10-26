<?php

declare(strict_types=1);

namespace App\ViewModels\HitAndRun;

final readonly class HitAndRunRowViewModel
{
    public function __construct(
        public int $id,
        public int $torrentId,
        public string $torrentName,
        public string $uploaded,
        public string $downloaded,
        public string $ratio,
        public bool $ratioLow,
        public ?string $completedAt,
        public int $status,
        public string $statusText,
        public string $comment,
        public ?int $seedDone,
        public ?float $seedRequired,
        public ?string $inspectEndsIn,
        public bool $canRemove,
        public bool $removeDisabled,
        public ?int $pointsShort,
    ) {}
}
