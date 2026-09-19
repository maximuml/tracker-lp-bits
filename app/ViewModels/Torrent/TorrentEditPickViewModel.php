<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

final readonly class TorrentEditPickViewModel
{
    /**
     * @param  list<array{value: string, label: string}>  $promotionDurations
     * @param  list<array{key: string, text: string}>|null  $posStates
     */
    public function __construct(
        public ?SafeHtml $promotionOptions,
        public int $promotionTimeType,
        public string $promotionUntil,
        public array $promotionDurations,
        public ?array $posStates,
        public string $posStateSelected,
        public string $posStateUntil,
        public string $specialLabel,
        public string $positionLabel,
        public SafeHtml $deadlineLabel,
    ) {}
}
