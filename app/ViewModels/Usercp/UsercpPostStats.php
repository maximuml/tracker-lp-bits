<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * Forum-post statistics shown in the usercp home section
 * (`N posts [view] (X per day; Y% of total)`).
 */
final readonly class UsercpPostStats
{
    public function __construct(
        public int $posts,
        public int $dayPosts,
        public string $percentages,
    ) {}
}
