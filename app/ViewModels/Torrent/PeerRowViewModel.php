<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

final readonly class PeerRowViewModel
{
    /**
     * @param  list<string>  $locationLines
     */
    public function __construct(
        public bool $highlighted,
        public bool $anonymous,
        public bool $revealUsername,
        public SafeHtml $username,
        public bool $revealLocation,
        public ?string $locationTitle,
        public array $locationLines,
        public bool $connectableYes,
        public string $uploaded,
        public string $uploadRate,
        public string $downloaded,
        public string $downloadRate,
        public ?string $ratioClass,
        public string $ratioText,
        public string $completePercent,
        public string $connected,
        public string $idle,
        public string $client,
    ) {}
}
