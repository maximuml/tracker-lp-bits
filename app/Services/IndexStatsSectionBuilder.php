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
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function buildStats(array $lang, LegacyRedisCache $cache): array
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
            'title' => $lang['text_tracker_statistics'] ?? 'Statistics',
            'userStats' => [
                'activeToday' => number_format($userStats['totalonlinetoday']),
                'activeThisWeek' => number_format($userStats['totalonlineweek']),
                'registered' => number_format($userStats['registered']).' / '.number_format($maxusers),
                'unconfirmed' => number_format($userStats['unverified']),
                'vip' => number_format($userStats['vip']),
                'vipLabel' => UserClass::name(UC_VIP, false, false, true),
                'donors' => number_format($userStats['donated']),
                'donorsLabel' => $lang['row_donors'] ?? 'Donors',
                'warned' => number_format($userStats['warned']),
                'warnedLabel' => $lang['row_warned_users'] ?? 'Warned',
                'banned' => number_format($userStats['disabled']),
                'bannedLabel' => $lang['row_banned_users'] ?? 'Banned',
                'male' => number_format($userStats['registered_male']),
                'maleLabel' => $lang['row_male_users'] ?? 'Male',
                'female' => number_format($userStats['registered_female']),
                'femaleLabel' => $lang['row_female_users'] ?? 'Female',
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
                'rowUsersActiveToday' => $lang['row_users_active_today'] ?? 'Active today',
                'rowUsersActiveThisWeek' => $lang['row_users_active_this_week'] ?? 'Active this week',
                'rowRegisteredUsers' => $lang['row_registered_users'] ?? 'Registered',
                'rowUnconfirmedUsers' => $lang['row_unconfirmed_users'] ?? 'Unconfirmed',
                'rowTorrents' => $lang['row_torrents'] ?? 'Torrents',
                'rowDeadTorrents' => $lang['row_dead_torrents'] ?? 'Dead',
                'rowSeeders' => $lang['row_seeders'] ?? 'Seeders',
                'rowLeechers' => $lang['row_leechers'] ?? 'Leechers',
                'rowPeers' => $lang['row_peers'] ?? 'Peers',
                'rowSeederLeecherRatio' => $lang['row_seeder_leecher_ratio'] ?? 'Ratio',
                'rowActiveBrowsingUsers' => $lang['row_active_browsing_users'] ?? 'Browsing',
                'rowTrackerActiveUsers' => $lang['row_tracker_active_users'] ?? 'Tracker active',
                'rowTotalSizeOfTorrents' => $lang['row_total_size_of_torrents'] ?? 'Total size',
                'rowTotalUploaded' => $lang['row_total_uploaded'] ?? 'Total uploaded',
                'rowTotalDownloaded' => $lang['row_total_downloaded'] ?? 'Total downloaded',
                'rowTotalData' => $lang['row_total_data'] ?? 'Total data',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function buildTrackerLoad(array $lang): array
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
            'title' => $lang['text_tracker_load'] ?? 'Tracker load',
            'load' => trim($load),
        ];
    }
}
