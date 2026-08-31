<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 1: peer and seed-bonus cleanup.
 */
final class PeerCleanupTask implements CleanupTask
{
    /**
     * Priority Class 1: remove peers whose last_action is older than the dead
     * threshold.
     */
    public function prunePeers(): string
    {
        $deadtime = date('Y-m-d H:i:s', Time::deadThreshold(
            (int) SiteConfig::current()->main->anninterthree(3600),
            time()
        ));

        DB::table('peers')->where('last_action', '<', $deadtime)->delete();

        return 'update peer status';
    }

    /**
     * Priority Class 1: reset per-hour seed bonus counters for users whose seeding
     * snapshot is older than two autoclean intervals.
     */
    public function resetSeedBonusCounters(): string
    {
        $interval = (int) SiteConfig::current()->main->autocleanIntervalOne(900);
        $cutoff = Carbon::now()->subSeconds(2 * $interval)->toDateTimeString();

        DB::table('users')
            ->where('seed_points_updated_at', '<', $cutoff)
            ->update([
                'seed_points_per_hour' => 0,
                'seed_bonus_per_hour' => 0,
                'seeding_torrent_count' => 0,
                'seeding_torrent_size' => 0,
            ]);

        return 'reset seed bonus counters';
    }

    public function run(): string
    {
        return $this->prunePeers();
    }
}
