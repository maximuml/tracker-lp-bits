<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * The peers row: seed/leech counts plus the show/hide peerlist toggles
 * (`data-peerlist` hooks handled by common.js).
 */
final class PeersRow
{
    public function __construct(
        public readonly int $torrentId,
        public readonly int $seeders,
        public readonly int $leechers,
    ) {}
}
