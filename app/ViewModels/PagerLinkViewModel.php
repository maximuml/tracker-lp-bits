<?php

declare(strict_types=1);

namespace App\ViewModels;

final readonly class PagerLinkViewModel
{
    public function __construct(
        public bool $dots,
        public string $start,
        public string $end,
        public ?string $url,
        public ?int $num = null,
    ) {}
}
