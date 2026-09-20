<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

final readonly class BdInfoDiscViewModel
{
    public function __construct(
        public ?string $heading,
        public ?BdInfoColumnViewModel $videos,
        public ?BdInfoColumnViewModel $audios,
        public ?BdInfoColumnViewModel $subtitles,
        public bool $trailingHr,
    ) {}
}
