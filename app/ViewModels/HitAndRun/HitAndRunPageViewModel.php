<?php

declare(strict_types=1);

namespace App\ViewModels\HitAndRun;

final readonly class HitAndRunPageViewModel
{
    /**
     * @param  array<int, int>  $statusCounts
     * @param  list<HitAndRunRowViewModel>  $rows
     */
    public function __construct(
        public bool $isEnabled,
        public float $price,
        public float $balance,
        public array $statusCounts,
        public array $rows,
        public ?float $seedTimeMinimum,
        public ?float $ignoreWhenRatioReach,
        public ?float $inspectTime,
        public ?float $banUserWhenCountsReach,
    ) {}
}
