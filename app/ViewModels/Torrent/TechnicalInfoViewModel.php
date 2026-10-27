<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;
use Illuminate\Contracts\Support\Htmlable;

final readonly class TechnicalInfoViewModel
{
    /**
     * @param  array<string, string>  $generalMain
     * @param  array<string, string>  $videosMain
     * @param  list<AudioTrackViewModel>  $audioTracks
     */
    public function __construct(
        public bool $rawOnly,
        public SafeHtml $rawSpoiler,
        public string $generalTitle = '',
        public bool $hasGeneral = false,
        public array $generalMain = [],
        public ?Htmlable $generalExtraSpoiler = null,
        public string $videoTitle = '',
        public bool $hasVideo = false,
        public array $videosMain = [],
        public ?Htmlable $encodingSpoiler = null,
        public string $audioTitle = '',
        public bool $hasAudio = false,
        public array $audioTracks = [],
        public ?Htmlable $hiddenAudioSpoiler = null,
    ) {}
}
