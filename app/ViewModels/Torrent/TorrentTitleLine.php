<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * The `<h1>` torrent-name line on the details page: escaped name,
 * banned marker and the badge cluster (paid / promotion / H&R /
 * approval) shared with the list rows.
 */
final class TorrentTitleLine
{
    public function __construct(
        public readonly string $name,
        public readonly bool $banned,
        public readonly TorrentBadgeSet $badges,
    ) {}
}
