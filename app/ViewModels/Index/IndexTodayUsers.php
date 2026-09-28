<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Today-active users strip: count plus hover cards keyed by user id.
 */
final readonly class IndexTodayUsers
{
    /**
     * @param  array<int, IndexTodayUserCard>  $cards
     */
    public function __construct(
        public int $count = 0,
        public array $cards = [],
    ) {}
}
