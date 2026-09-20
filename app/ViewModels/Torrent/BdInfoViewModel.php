<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

final readonly class BdInfoViewModel
{
    /**
     * @param  list<BdInfoDiscViewModel>  $discs
     */
    public function __construct(
        public bool $rawOnly,
        public SafeHtml $rawSpoiler,
        public array $discs = [],
    ) {}
}
