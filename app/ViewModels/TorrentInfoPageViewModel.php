<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the torrent_info page (torrent_info/index.blade.php).
 *
 * Assembled by TorrentMaintenanceController::torrentinfo().
 */
final class TorrentInfoPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string $torrentName,
        public readonly SafeHtml $structureHtml,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
