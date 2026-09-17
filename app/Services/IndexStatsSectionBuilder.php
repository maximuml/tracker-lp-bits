<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\IndexRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\Globals;
use App\Support\UserClass;

/**
 * Builds the statistics and tracker-load sections of the index page.
 * Extracted from IndexPageService to keep both classes under the
 * 400-line ratchet.
 */
final class IndexStatsSectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
        private readonly IndexRepository $indexRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildStats(LegacyRedisCache $cache): array
    {
        $show = $this->globals->get('showstats_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $userStats = $this->indexRepository->getUserStats();
        $torrentStats = $this->indexRepository->getTorrentStats();
        $classStats = $this->indexRepository->getClassStats();
        $maxusers = (int) $this->globals->get('maxusers', 0);

        return [
            'show' => true,
            'title' => __('legacy/index.text_tracker_statistics'),
            'userStats' => [
                'activeToday' => number_format($userStats['totalonlinetoday']),
                'activeThisWeek' => number_format($userStats['totalonlineweek']),
                'registered' => number_format($userStats['registered']).' / '.number_format($maxusers),
                'unconfirmed' => number_format($userStats['unverified']),
                'vip' => number_format($userStats['vip']),
                'vipLabel' => UserClass::name(UC_VIP, false, false, true),
                'donors' => number_format($userStats['donated']),
                'donorsLabel' => __('legacy/index.row_donors'),
                'warned' => number_format($userStats['warned']),
                'warnedLabel' => __('legacy/index.row_warned_users'),
                'banned' => number_format($userStats['disabled']),
                'bannedLabel' => __('legacy/index.row_banned_users'),
                'male' => number_format($userStats['registered_male']),
                'maleLabel' => __('legacy/index.row_male_users'),
                'female' => number_format($userStats['registered_female']),
                'femaleLabel' => __('legacy/index.row_female_users'),
            ],
            'torrentStats' => [
                'torrents' => number_format($torrentStats['torrents']),
                'dead' => number_format($torrentStats['dead']),
                'seeders' => number_format($torrentStats['seeders']),
                'leechers' => number_format($torrentStats['leechers']),
                'peers' => number_format($torrentStats['peers']),
                'ratio' => $torrentStats['ratio'].'%',
                'activeBrowsing' => number_format($torrentStats['activewebusernow']),
                'trackerActive' => number_format($torrentStats['activetrackerusernow']),
                'totalSize' => Format::size($torrentStats['totaltorrentssize']),
                'totalUploaded' => Format::size($torrentStats['totaluploaded']),
                'totalDownloaded' => Format::size($torrentStats['totaldownloaded']),
                'totalData' => Format::size($torrentStats['totaldata']),
            ],
            'classStats' => [
                ['label' => UserClass::name(UC_PEASANT, false, false, true), 'value' => number_format($classStats[UC_PEASANT]), 'icon' => 'leechwarned'],
                ['label' => UserClass::name(UC_USER, false, false, true), 'value' => number_format($classStats[UC_USER])],
                ['label' => UserClass::name(UC_POWER_USER, false, false, true), 'value' => number_format($classStats[UC_POWER_USER])],
                ['label' => UserClass::name(UC_ELITE_USER, false, false, true), 'value' => number_format($classStats[UC_ELITE_USER])],
                ['label' => UserClass::name(UC_CRAZY_USER, false, false, true), 'value' => number_format($classStats[UC_CRAZY_USER])],
                ['label' => UserClass::name(UC_INSANE_USER, false, false, true), 'value' => number_format($classStats[UC_INSANE_USER])],
                ['label' => UserClass::name(UC_VETERAN_USER, false, false, true), 'value' => number_format($classStats[UC_VETERAN_USER])],
                ['label' => UserClass::name(UC_EXTREME_USER, false, false, true), 'value' => number_format($classStats[UC_EXTREME_USER])],
                ['label' => UserClass::name(UC_ULTIMATE_USER, false, false, true), 'value' => number_format($classStats[UC_ULTIMATE_USER])],
                ['label' => UserClass::name(UC_NEXUS_MASTER, false, false, true), 'value' => number_format($classStats[UC_NEXUS_MASTER])],
            ],
            'labels' => [
                'rowUsersActiveToday' => __('legacy/index.row_users_active_today'),
                'rowUsersActiveThisWeek' => __('legacy/index.row_users_active_this_week'),
                'rowRegisteredUsers' => __('legacy/index.row_registered_users'),
                'rowUnconfirmedUsers' => __('legacy/index.row_unconfirmed_users'),
                'rowTorrents' => __('legacy/index.row_torrents'),
                'rowDeadTorrents' => __('legacy/index.row_dead_torrents'),
                'rowSeeders' => __('legacy/index.row_seeders'),
                'rowLeechers' => __('legacy/index.row_leechers'),
                'rowPeers' => __('legacy/index.row_peers'),
                'rowSeederLeecherRatio' => __('legacy/index.row_seeder_leecher_ratio'),
                'rowActiveBrowsingUsers' => __('legacy/index.row_active_browsing_users'),
                'rowTrackerActiveUsers' => __('legacy/index.row_tracker_active_users'),
                'rowTotalSizeOfTorrents' => __('legacy/index.row_total_size_of_torrents'),
                'rowTotalUploaded' => __('legacy/index.row_total_uploaded'),
                'rowTotalDownloaded' => __('legacy/index.row_total_downloaded'),
                'rowTotalData' => __('legacy/index.row_total_data'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTrackerLoad(): array
    {
        $show = $this->globals->get('showtrackerload', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $loadAvg = sys_getloadavg();
        if ($loadAvg === false) {
            $loadAvg = [0.0, 0.0, 0.0];
        }
        $load = sprintf('load average: %.2f, %.2f, %.2f', $loadAvg[0], $loadAvg[1], $loadAvg[2]);

        return [
            'show' => $load !== '',
            'title' => __('legacy/index.text_tracker_load'),
            'load' => trim($load),
        ];
    }
}
