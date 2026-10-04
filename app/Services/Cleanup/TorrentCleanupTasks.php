<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Services\Cleanup\Tasks\DeadTorrentAndLogCleanupTask;
use App\Services\Cleanup\Tasks\PeerCleanupTask;
use App\Services\Cleanup\Tasks\TorrentPromotionCleanupTask;
use App\Services\Cleanup\Tasks\TorrentVisibilityTask;

/**
 * Parameter object bundling the tracker-data cleanup tasks — keeps the
 * Tasks coordinator's constructor within the RepositorySizeTest cap.
 */
final readonly class TorrentCleanupTasks
{
    public function __construct(
        public PeerCleanupTask $peerCleanup,
        public TorrentVisibilityTask $torrentVisibility,
        public TorrentPromotionCleanupTask $torrentPromotionCleanup,
        public DeadTorrentAndLogCleanupTask $deadTorrentAndLogCleanup,
    ) {}
}
