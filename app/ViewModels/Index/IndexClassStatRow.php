<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

use App\Support\Html\SafeHtml;

/**
 * One user-class count row in the tracker statistics table.
 */
final readonly class IndexClassStatRow
{
    public function __construct(
        public SafeHtml $label,
        public string $value,
        public ?string $icon = null,
    ) {}
}
