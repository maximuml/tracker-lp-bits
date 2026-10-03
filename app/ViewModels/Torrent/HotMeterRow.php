<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Support\Html\SafeHtml;

/**
 * The "Hot Meter" row: views, hits, snatched count (linking to
 * viewsnatches.php) and last-seeder time.
 *
 * `snatchesPre`/`snatchesPost` are the two text parts rendered around
 * the `<b>` — the structural tags live in the Blade view.
 * `lastSeederLabel` is the entity-decoded `row_last_seeder` text.
 */
final class HotMeterRow
{
    public function __construct(
        public readonly int|string $views,
        public readonly int|string $hits,
        public readonly int|string $timesCompleted,
        public readonly int $torrentId,
        public readonly SafeHtml $lastSeeder,
        public readonly string $lastSeederLabel,
        public readonly string $snatchesPre,
        public readonly string $snatchesPost,
    ) {}
}
