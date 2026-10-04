<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\TorrentPosState;
use App\Repositories\TorrentCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Time;

/**
 * Priority Class 2 & 3: torrent visibility and sticky position expiration.
 */
final class TorrentVisibilityTask implements CleanupTask
{
    public function __construct(
        private readonly TorrentCleanupRepository $torrentCleanup,
    ) {}

    /**
     * Priority Class 2: mark torrents with no seeders and stale last_action as
     * invisible.
     */
    public function updateTorrentVisibility(): string
    {
        $maxDeadTime = (int) SiteConfig::current()->main->maxDeadTorrentTime(21600);
        $deadtime = Time::deadThreshold((int) SiteConfig::current()->main->anninterthree(3600), time()) - $maxDeadTime;
        $lastActionDeadTime = date('Y-m-d H:i:s', $deadtime);

        $this->torrentCleanup->markDeadVisibleTorrentsInvisible($lastActionDeadTime);

        return "update torrents' visibility";
    }

    /**
     * Priority Class 3: expire sticky position states.
     */
    public function expireTorrentSticky(): string
    {
        $toBeExpirePosStates = [TorrentPosState::STICKY_FIRST->value, TorrentPosState::STICKY_SECOND->value];

        $this->torrentCleanup->expireStickyPositions($toBeExpirePosStates, TorrentPosState::NONE->value);

        return 'expire torrent pos state';
    }

    public function run(): string
    {
        return $this->updateTorrentVisibility();
    }
}
