<?php

declare(strict_types=1);

namespace App\ViewModels;

final readonly class PagerViewModel
{
    /**
     * @param  list<PagerLinkViewModel>  $links
     */
    public function __construct(
        public string $prevTitle,
        public string $prevLabel,
        public ?string $prevUrl,
        public string $nextTitle,
        public string $nextLabel,
        public ?string $nextUrl,
        public array $links,
    ) {}
}
