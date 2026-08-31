<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\TorrentPosState;
use App\Models\Torrent;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Time;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 2 & 3: torrent visibility and sticky position expiration.
 */
final class TorrentVisibilityTask implements CleanupTask
{
    /**
     * Priority Class 2: mark torrents with no seeders and stale last_action as
     * invisible.
     */
    public function updateTorrentVisibility(): string
    {
        $maxDeadTime = (int) SiteConfig::current()->main->maxDeadTorrentTime(21600);
        $deadtime = Time::deadThreshold((int) SiteConfig::current()->main->anninterthree(3600), time()) - $maxDeadTime;
        $lastActionDeadTime = date('Y-m-d H:i:s', $deadtime);

        DB::table('torrents')
            ->where('visible', 1)
            ->where('last_action', '<', $lastActionDeadTime)
            ->where('seeders', 0)
            ->update(['visible' => 0]);

        return "update torrents' visibility";
    }

    /**
     * Priority Class 3: expire sticky position states.
     */
    public function expireTorrentSticky(): string
    {
        $toBeExpirePosStates = [TorrentPosState::STICKY_FIRST->value, TorrentPosState::STICKY_SECOND->value];

        Torrent::query()
            ->whereIn('pos_state', $toBeExpirePosStates)
            ->whereNotNull('pos_state_until')
            ->where('pos_state_until', '<', now())
            ->update([
                'pos_state' => TorrentPosState::NONE->value,
                'pos_state_until' => null,
            ]);

        return 'expire torrent pos state';
    }

    public function run(): string
    {
        return $this->updateTorrentVisibility();
    }
}
