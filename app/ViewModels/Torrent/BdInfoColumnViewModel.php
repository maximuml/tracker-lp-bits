<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

final readonly class BdInfoColumnViewModel
{
    /**
     * @param  array<string, string>  $visibleRows
     */
    public function __construct(
        public array $visibleRows,
        public ?SafeHtml $hiddenSpoiler,
    ) {}
}
