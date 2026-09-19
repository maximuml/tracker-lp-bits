<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * Seeding/leeching progress for the current user on a torrent row —
 * replaces `TorrentStatus::renderProgressBar()` HTML.
 */
final class TorrentProgress
{
    public function __construct(
        public readonly string $status,
        public readonly float $percent,
    ) {}
}
