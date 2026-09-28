<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Server load average line on the index page.
 */
final readonly class IndexTrackerLoadSection
{
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public string $load = '',
    ) {}
}
