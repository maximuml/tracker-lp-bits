<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * One result bar of the current poll on the index page.
 */
final readonly class IndexPollBar
{
    public function __construct(
        public string $option,
        public int $percent,
        public bool $selected,
    ) {}
}
