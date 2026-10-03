<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Browser-compatibility note at the bottom of the index page.
 */
final readonly class IndexBrowserNoteSection
{
    public function __construct(
        public bool $show = false,
    ) {}
}
