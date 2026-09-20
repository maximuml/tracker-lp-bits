<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

final readonly class PeerTableViewModel
{
    /**
     * @param  list<PeerRowViewModel>  $rows
     */
    public function __construct(
        public string $name,
        public int $count,
        public bool $showLocationColumn,
        public array $rows,
    ) {}
}
