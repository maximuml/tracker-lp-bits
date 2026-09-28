<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Disclaimer text section on the index page.
 */
final readonly class IndexDisclaimerSection
{
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public string $content = '',
    ) {}
}
