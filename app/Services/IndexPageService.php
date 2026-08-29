<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Poll;
use App\Models\Setting;
use App\Repositories\IndexRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CoverThumb;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Shoutbox;
use App\Support\UserClass;
use App\Support\UserDisplay;

/**
 * Prepares section data for the index page, replacing the legacy
 * index_content.php partial with typed Blade-rendered sections.
 */
final class IndexPageService
{
    /** @return array<string, mixed> */
    public function build(): array
    {
        $curUser = (array) (app(CurrentUser::class)->get() ?? []);
        $lang = (array) (app(Globals::class)->get('lang_index') ?? []);
        $cache = app(LegacyRedisCache::class);

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'canNewsManage' => Permission::can(PermissionEnum::NEWS_MANAGE),
            'canPollManage' => Permission::can(PermissionEnum::POLL_MANAGE),
            'canSbManage' => Permission::can(PermissionEnum::SB_MANAGE),
            'canLog' => Permission::can(PermissionEnum::LOG),
        ];

        // News
        $data['news'] = $this->buildNews($lang, $data['canNewsManage'], $cache);

        // Shoutbox
        $data['shoutbox'] = $this->buildShoutbox($lang, $data['canSbManage'], (int) ($curUser['id'] ?? 0));

        $data['extraModules'] = '';

        // Latest forum posts
        $data['forumPosts'] = $this->buildForumPosts($lang, $curUser);

        // Latest torrents
        $data['latestTorrents'] = $this->buildLatestTorrents($lang, $cache);

        // Top uploaders
        $data['topUploaders'] = $this->buildTopUploaders($lang);

        // Polls
        $data['polls'] = $this->buildPolls($lang, $curUser, $data['canPollManage'], $data['canLog'], $cache);

        // Stats
        $data['stats'] = $this->buildStats($lang, $cache);

        // Tracker load
        $data['trackerLoad'] = $this->buildTrackerLoad($lang);

        // Disclaimer
        $data['disclaimer'] = $this->buildDisclaimer($lang);

        // Browser note
        $data['browserNote'] = $this->buildBrowserNote($lang);

        // Reset unread news count
        if (! empty($curUser['id']) && $cache !== null) {
            $cache->delete_value('user_'.(int) $curUser['id'].'_unread_news_count');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildNews(array $lang, bool $canManage, ?LegacyRedisCache $cache): array
    {
        $maxNews = (int) app(Globals::class)->get('maxnewsnum_main', 0);

        return [
            'show' => true,
            'title' => $lang['text_recent_news'] ?? 'Recent news',
            'canManage' => $canManage,
            'manageLink' => $lang['text_news_page'] ?? 'News page',
            'items' => IndexRepository::getLatestNews($maxNews),
            'showHideTitle' => $lang['title_show_or_hide'] ?? 'Show/Hide',
            'editLabel' => $lang['text_e'] ?? 'E',
            'deleteLabel' => $lang['text_d'] ?? 'D',
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildShoutbox(array $lang, bool $canManage, int $userId): array
    {
        $show = app(Globals::class)->get('showshoutbox_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $csrf = Shoutbox::csrfToken($userId);
        AssetAppender::js("var SHOUT_CSRF = '".addslashes($csrf)."';", 'footer', false);

        $clearJs = '';
        if ($canManage) {
            $clearJs = <<<JS
jQuery('#clear-shout-box').on("click", function () {
    layer.confirm("{$lang['sure_to_clear_shout_box']}", {title: "Info", btn: ['Yes', "Cancel"], btnAlign: 'c'}, function (layerIndex) {
        jQuery.post("ajax.php", {"action": "clearShoutBox", "params": {"csrf": (typeof SHOUT_CSRF !== 'undefined' ? SHOUT_CSRF : '')}}, function (response) {
            layer.close(layerIndex)
            if (response.ret != 0) {
                layer.alert(response.msg, {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'})
            } else {
                document.getElementById('iframe-shout-box').src='shoutbox.php?type=shoutbox';
            }
        }, "json")
    })
})
JS;
            AssetAppender::js($clearJs, 'footer', false);
        }

        return [
            'show' => true,
            'title' => $lang['text_shoutbox'] ?? 'Shoutbox',
            'autoRefreshLabel' => $lang['text_auto_refresh_after'] ?? 'Auto refresh after',
            'secondsLabel' => $lang['text_seconds'] ?? 'seconds',
            'historyLabel' => $lang['text_shoutbox_history'] ?? 'History',
            'canManage' => $canManage,
            'clearLabel' => $lang['clear_shout_box'] ?? 'Clear',
            'toolbar' => Shoutbox::toolbar('shbox', 'shbox_text'),
            'messageLabel' => $lang['text_message'] ?? 'Message',
            'submitLabel' => $lang['sumbit_shout'] ?? 'Shout',
            'clearButtonLabel' => $lang['submit_clear'] ?? 'Clear',
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildForumPosts(array $lang, array $curUser): array
    {
        $show = app(Globals::class)->get('showlastxforumposts_main', '') === 'yes' && ! empty($curUser);

        if (! $show) {
            return ['show' => false];
        }

        $posts = IndexRepository::getLatestForumPosts(5, (int) UserDisplay::currentClass());

        return [
            'show' => count($posts) > 0,
            'title' => $lang['text_last_five_posts'] ?? 'Last five posts',
            'colTopicTitle' => $lang['col_topic_title'] ?? 'Topic',
            'colView' => $lang['col_view'] ?? 'Views',
            'colAuthor' => $lang['col_author'] ?? 'Author',
            'colPostedAt' => $lang['col_posted_at'] ?? 'Posted at',
            'textIn' => $lang['text_in'] ?? 'in ',
            'items' => $posts,
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildLatestTorrents(array $lang, ?LegacyRedisCache $cache): array
    {
        $show = app(Globals::class)->get('showlastxtorrents_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $cacheKey = 'index_latest_torrents_grid_v2';
        $cacheTtl = 120;
        $html = $cache !== null ? $cache->get_value($cacheKey) : false;

        if ($html === false || $html === null || $html === '') {
            $torrents = IndexRepository::getLatestTorrents(9);
            if ($torrents->isNotEmpty()) {
                $items = [];
                foreach ($torrents as $torrent) {
                    $detailsUrl = 'details.php?id='.(int) $torrent->id.'&hit=1';
                    $rawCover = trim((string) ($torrent->cover ?? ''));
                    $thumbUrl = $rawCover !== '' ? CoverThumb::urlWithContext((string) $rawCover, (int) 240, (int) 360, (int) 82) : '';
                    $typeLabel = trim((string) ($torrent->basic_category->name ?? ''));
                    if (($torrent->anonymous ?? 'no') === 'yes') {
                        $ownerHtml = '<i>Anonymous</i>';
                    } else {
                        $ownerHtml = UserDisplay::username((int) $torrent->owner);
                    }
                    $items[] = [
                        'detailsUrl' => $detailsUrl,
                        'thumbUrl' => $thumbUrl,
                        'typeLabel' => $typeLabel,
                        'ownerHtml' => $ownerHtml,
                        'nameSafe' => htmlspecialchars($torrent->name),
                        'nameShort' => htmlspecialchars(mb_substr($torrent->name, 0, 60)),
                        'seeders' => (int) $torrent->seeders,
                        'leechers' => (int) $torrent->leechers,
                        'size' => Format::size((int) $torrent->size),
                    ];
                }
                $html = view('index.sections.latest_torrents', [
                    'items' => $items,
                    'title' => $lang['text_last_five_torrent'] ?? 'Latest torrents',
                    'colSeeder' => $lang['col_seeder'] ?? 'Seeders',
                    'colLeecher' => $lang['col_leecher'] ?? 'Leechers',
                ])->render();
                $cache?->cache_value($cacheKey, $html, $cacheTtl);
            } else {
                $html = '';
                $cache?->cache_value($cacheKey, $html, $cacheTtl);
            }
        }

        return ['show' => true, 'html' => $html];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildTopUploaders(array $lang): array
    {
        if (! SiteConfig::current()->main->showTopUploader()) {
            return ['show' => false];
        }

        $allUploaders = IndexRepository::getTopUploaders(10);
        if ($allUploaders->isEmpty()) {
            return ['show' => false];
        }

        AssetAppender::css('.tr-top-uploader-tab>td {cursor: pointer}', 'footer', false);
        $toggleJs = <<<'JS'
jQuery(".tr-top-uploader-tab").on("click", "td", function () {
    let _this = jQuery(this)
    if (_this.hasClass("colhead")) {
        return
    }
    _this.parent().children().removeClass("colhead")
    _this.addClass("colhead")
    jQuery(".top-uploader").hide()
    jQuery("." + _this.attr("data-table")).fadeIn()

})
JS;
        AssetAppender::js($toggleJs, 'footer', false);

        $recentUploaders = IndexRepository::getTopUploaders(10, 30);

        $buildRows = function ($uploaders): array {
            $rows = [];
            foreach ($uploaders as $ranking => $uploader) {
                $rows[] = [
                    'username' => UserDisplay::username($uploader->id),
                    'count' => $uploader->count,
                    'rank' => $ranking + 1,
                ];
            }

            return $rows;
        };

        return [
            'show' => true,
            'title' => $lang['top_uploader_title'] ?? 'Top uploaders',
            'toggleHint' => $lang['top_uploader_toggle_time_range_tab'] ?? '',
            'recentlyLabel' => $lang['top_uploader_toggle_time_range_recently'] ?? 'Recently',
            'allLabel' => $lang['top_uploader_toggle_time_range_all'] ?? 'All time',
            'colAuthor' => $lang['col_author'] ?? 'Author',
            'colCounts' => $lang['col_counts'] ?? 'Count',
            'colRanking' => $lang['col_ranking'] ?? 'Rank',
            'allRows' => $buildRows($allUploaders),
            'recentRows' => $buildRows($recentUploaders),
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildPolls(array $lang, array $curUser, bool $canManage, bool $canLog, ?LegacyRedisCache $cache): array
    {
        $show = ! empty($curUser) && app(Globals::class)->get('showpolls_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $pollArr = $cache !== null ? $cache->get_value('current_poll_content') : false;
        if ($pollArr === false || $pollArr === null) {
            $pollArr = IndexRepository::getCurrentPoll();
            if ($pollArr && $cache !== null) {
                $cache->cache_value('current_poll_content', $pollArr, 7226);
            }
        }

        $pollExists = ! empty($pollArr);

        $result = [
            'show' => true,
            'title' => $lang['text_polls'] ?? 'Polls',
            'canManage' => $canManage,
            'newLabel' => $lang['text_new'] ?? 'New',
            'editLabel' => $lang['text_edit'] ?? 'Edit',
            'deleteLabel' => $lang['text_delete'] ?? 'Delete',
            'detailLabel' => $lang['text_detail'] ?? 'Detail',
            'exists' => $pollExists,
        ];

        if ($pollExists) {
            $pollid = (int) ($pollArr['id'] ?? 0);
            $question = (string) ($pollArr['question'] ?? '');
            $options = [];
            for ($i = 0; $i <= Poll::MAX_OPTION_INDEX; $i++) {
                $opt = (string) ($pollArr["option{$i}"] ?? '');
                if ($opt !== '') {
                    $options[$i] = $opt;
                }
            }

            $uservote = IndexRepository::getUserVote($pollid, (int) ($curUser['id'] ?? 0));
            $result['pollId'] = $pollid;
            $result['question'] = $question;
            $result['options'] = $options;
            $result['hasVoted'] = $uservote !== null;
            $result['blankVoteLabel'] = $lang['radio_blank_vote'] ?? 'Blank vote';
            $result['submitVoteLabel'] = $lang['submit_vote'] ?? 'Vote';
            $result['canLog'] = $canLog;
            $result['previousPollsLabel'] = $lang['text_previous_polls'] ?? 'Previous polls';
            $result['votesLabel'] = $lang['text_votes'] ?? 'Votes';

            if ($uservote !== null) {
                $results = $cache !== null ? $cache->get_value('current_poll_result') : false;
                if ($results === false || $results === null) {
                    $results = IndexRepository::getPollResults($pollid);
                    $cache?->cache_value('current_poll_result', $results, 3652);
                }
                $tvotes = array_sum(array_column($results, 'count'));
                $bars = [];
                foreach ($results as $item) {
                    $p = $tvotes == 0 ? 0 : (int) round($item['count'] / $tvotes * 100);
                    $bars[] = [
                        'option' => $item['option'],
                        'percent' => $p,
                        'width' => $p * 3,
                        'selected' => $item['index'] == $uservote,
                    ];
                }
                $result['bars'] = $bars;
                $result['totalVotes'] = number_format($tvotes);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildStats(array $lang, ?LegacyRedisCache $cache): array
    {
        $show = app(Globals::class)->get('showstats_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $userStats = IndexRepository::getUserStats();
        $torrentStats = IndexRepository::getTorrentStats();
        $classStats = IndexRepository::getClassStats();
        $maxusers = (int) app(Globals::class)->get('maxusers', 0);

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
    private function buildTrackerLoad(array $lang): array
    {
        $show = app(Globals::class)->get('showtrackerload', '') === 'yes';

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

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildDisclaimer(array $lang): array
    {
        $siteName = Setting::getSiteName();

        return [
            'show' => true,
            'title' => $lang['text_disclaimer'] ?? 'Disclaimer',
            'content' => sprintf($lang['text_disclaimer_content'] ?? '', $siteName, $siteName),
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildBrowserNote(array $lang): array
    {
        return [
            'show' => true,
            'note' => $lang['text_browser_note'] ?? '',
            'nexusUrl' => (string) app(Globals::class)->get('NEXUSPHPURL', ''),
            'projectName' => (string) app(Globals::class)->get('PROJECTNAME', ''),
        ];
    }
}
