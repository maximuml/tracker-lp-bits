<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

/**
 * The per-user torrent history table for `getusertorrentlistajax`.
 * Column visibility mirrors the per-mode flag matrix previously encoded
 * in `TorrentAjaxController::torrentListTable()`; rows arrive prepared
 * as {@see UserTorrentRow} objects.
 */
final class UserTorrentListViewModel
{
    /**
     * @param  list<UserTorrentRow>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $showSize,
        public readonly bool $showSeeders,
        public readonly bool $showLeechers,
        public readonly bool $showUploaded,
        public readonly bool $showDownloaded,
        public readonly bool $showRatio,
        public readonly bool $showSeedTime,
        public readonly bool $showLeechTime,
        public readonly bool $showCompletedAt,
        public readonly bool $showAnonymous,
        public readonly bool $showClient,
    ) {}
}
