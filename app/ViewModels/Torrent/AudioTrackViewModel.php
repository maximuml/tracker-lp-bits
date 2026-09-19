<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

final readonly class AudioTrackViewModel
{
    /**
     * @param  array<string, string>  $rows
     * @param  list<string>  $badges
     */
    public function __construct(
        public string $head,
        public array $badges,
        public array $rows,
    ) {}
}
