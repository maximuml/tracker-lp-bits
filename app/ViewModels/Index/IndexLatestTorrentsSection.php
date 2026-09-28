<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

use App\Support\Html\SafeHtml;

/**
 * Latest torrents card grid on the index page. The grid markup is
 * pre-rendered (and cached) by the section blade `index.sections.latest_torrents`.
 */
final readonly class IndexLatestTorrentsSection
{
    public function __construct(
        public bool $show = false,
        public ?SafeHtml $html = null,
    ) {}
}
