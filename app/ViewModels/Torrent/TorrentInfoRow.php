<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * The "Torrent Info" row cells: file count with the show/hide filelist
 * toggles (multi-file torrents), info hash, and the optional torrent
 * structure link.
 */
final class TorrentInfoRow
{
    public function __construct(
        public readonly int $torrentId,
        public readonly ?int $numFiles,
        public readonly string $infoHash,
        public readonly bool $showStructure,
    ) {}
}
