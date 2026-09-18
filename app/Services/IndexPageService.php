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
use App\Support\Html\SafeHtml;
use App\Support\Shoutbox;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\ViewModels\IndexPageViewModel;
use Illuminate\Support\HtmlString;

/**
 * Prepares section data for the index page, replacing the legacy
 * index_content.php partial with typed Blade-rendered sections.
 * Poll/stats/meta sections are delegated to dedicated builders.
 */
final class IndexPageService
{
    private readonly CoverThumb $coverThumb;

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly LegacyRedisCache $cache,
        private readonly IndexRepository $indexRepository,
        ?CoverThumb $coverThumb = null,
    ) {
        $this->coverThumb = $coverThumb ?? new CoverThumb($this->cache);
    }

    public function build(): IndexPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);

        $data = [
            'curUser' => $curUser,
            'canNewsManage' => Permission::can(PermissionEnum::NEWS_MANAGE),
            'canPollManage' => Permission::can(PermissionEnum::POLL_MANAGE),
            'canSbManage' => Permission::can(PermissionEnum::SB_MANAGE),
            'canLog' => Permission::can(PermissionEnum::LOG),
        ];

        // News
        $data['news'] = $this->buildNews($data['canNewsManage'], $this->cache);

        // Shoutbox
        $data['shoutbox'] = $this->buildShoutbox($data['canSbManage'], (int) ($curUser['id'] ?? 0));

        $data['extraModules'] = '';

        // Latest forum posts
        $data['forumPosts'] = $this->buildForumPosts($curUser);

        // Latest torrents
        $data['latestTorrents'] = $this->buildLatestTorrents($this->cache);

        // Top uploaders
        $data['topUploaders'] = $this->buildTopUploaders();

        // Polls
        $data['polls'] = $this->buildPolls($curUser, $data['canPollManage'], $data['canLog'], $this->cache);

        // Stats
        $data['stats'] = $this->buildStats($this->cache);

        // Tracker load
        $data['trackerLoad'] = $this->buildTrackerLoad();

        // Disclaimer
        $data['disclaimer'] = $this->buildDisclaimer();

        // Browser note
        $data['browserNote'] = $this->buildBrowserNote();

        // Reset unread news count
        if (! empty($curUser['id'])) {
            $this->cache->delete_value('user_'.(int) $curUser['id'].'_unread_news_count');
        }

        return new IndexPageViewModel(
            curUser: $data['curUser'],
            canNewsManage: $data['canNewsManage'],
            canPollManage: $data['canPollManage'],
            canSbManage: $data['canSbManage'],
            canLog: $data['canLog'],
            news: $data['news'],
            shoutbox: $data['shoutbox'],
            extraModules: $data['extraModules'],
            forumPosts: $data['forumPosts'],
            latestTorrents: $data['latestTorrents'],
            topUploaders: $data['topUploaders'],
            polls: $data['polls'],
            stats: $data['stats'],
            trackerLoad: $data['trackerLoad'],
            disclaimer: $data['disclaimer'],
            browserNote: $data['browserNote'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNews(bool $canManage, LegacyRedisCache $cache): array
    {
        $maxNews = (int) $this->globals->get('maxnewsnum_main', 0);

        return [
            'show' => true,
            'title' => __('legacy/index.text_recent_news'),
            'canManage' => $canManage,
            'manageLink' => __('legacy/index.text_news_page'),
            'items' => $this->indexRepository->getLatestNews($maxNews),
            'showHideTitle' => __('legacy/index.title_show_or_hide'),
            'editLabel' => __('legacy/index.text_e'),
            'deleteLabel' => __('legacy/index.text_d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildShoutbox(bool $canManage, int $userId): array
    {
        $show = $this->globals->get('showshoutbox_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $csrf = Shoutbox::csrfToken($userId);
        AssetAppender::js("var SHOUT_CSRF = '".addslashes($csrf)."';", 'footer', false);

        $clearJs = '';
        if ($canManage) {
            $sureToClear = __('legacy/index.sure_to_clear_shout_box');
            $clearJs = <<<JS
document.getElementById('clear-shout-box').addEventListener("click", function () {
    layer.confirm("{$sureToClear}", {title: "Info", btn: ['Yes', "Cancel"], btnAlign: 'c'}, function (layerIndex) {
        nativePost("ajax.php", {"action": "clearShoutBox", "params": {"csrf": (typeof SHOUT_CSRF !== 'undefined' ? SHOUT_CSRF : '')}}, function (response) {
            layer.close(layerIndex)
            if (response.ret != 0) {
                layer.alert(response.msg, {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'})
            } else {
                document.getElementById('iframe-shout-box').src='shoutbox.php?type=shoutbox';
            }
        })
    })
})
JS;
            AssetAppender::js($clearJs, 'footer', false);
        }

        return [
            'show' => true,
            'title' => __('legacy/index.text_shoutbox'),
            'autoRefreshLabel' => __('legacy/index.text_auto_refresh_after'),
            'secondsLabel' => __('legacy/index.text_seconds'),
            'historyLabel' => __('legacy/index.text_shoutbox_history'),
            'canManage' => $canManage,
            'clearLabel' => __('legacy/index.clear_shout_box'),
            'toolbar' => SafeHtml::fromTrustedHtml(Shoutbox::toolbar('shbox', 'shbox_text')),
            'messageLabel' => __('legacy/index.text_message'),
            'submitLabel' => __('legacy/index.sumbit_shout'),
            'clearButtonLabel' => __('legacy/index.submit_clear'),
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildForumPosts(array $curUser): array
    {
        $show = $this->globals->get('showlastxforumposts_main', '') === 'yes' && ! empty($curUser);

        if (! $show) {
            return ['show' => false];
        }

        $posts = $this->indexRepository->getLatestForumPosts(5, (int) UserDisplay::currentClass());

        return [
            'show' => count($posts) > 0,
            'title' => __('legacy/index.text_last_five_posts'),
            'colTopicTitle' => __('legacy/index.col_topic_title'),
            'colView' => __('legacy/index.col_view'),
            'colAuthor' => __('legacy/index.col_author'),
            'colPostedAt' => __('legacy/index.col_posted_at'),
            'textIn' => __('legacy/index.text_in'),
            'items' => $posts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLatestTorrents(LegacyRedisCache $cache): array
    {
        $show = $this->globals->get('showlastxtorrents_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $cacheKey = 'index_latest_torrents_grid_v2';
        $cacheTtl = 120;
        $html = $cache->get_value($cacheKey);

        if ($html === false || $html === null || $html === '') {
            $torrents = $this->indexRepository->getLatestTorrents(9);
            if ($torrents->isNotEmpty()) {
                $items = [];
                foreach ($torrents as $torrent) {
                    $detailsUrl = 'details.php?id='.(int) $torrent->id.'&hit=1';
                    $rawCover = trim((string) ($torrent->cover ?? ''));
                    $thumbUrl = $rawCover !== '' ? $this->coverThumb->urlWithContext((string) $rawCover, (int) 240, (int) 360, (int) 82) : '';
                    $typeLabel = trim((string) ($torrent->basic_category->name ?? ''));
                    if ($torrent->anonymous) {
                        $ownerHtml = '<i>Anonymous</i>';
                    } else {
                        $ownerHtml = UserDisplay::username((int) $torrent->owner);
                    }
                    $items[] = [
                        'detailsUrl' => $detailsUrl,
                        'thumbUrl' => $thumbUrl,
                        'typeLabel' => $typeLabel,
                        'ownerHtml' => SafeHtml::fromTrustedHtml($ownerHtml),
                        'name' => (string) $torrent->name,
                        'nameShort' => mb_substr((string) $torrent->name, 0, 60),
                        'seeders' => (int) $torrent->seeders,
                        'leechers' => (int) $torrent->leechers,
                        'size' => Format::size((int) $torrent->size),
                    ];
                }
                $html = view('index.sections.latest_torrents', [
                    'items' => $items,
                    'title' => __('legacy/index.text_last_five_torrent'),
                    'colSeeder' => __('legacy/index.col_seeder'),
                    'colLeecher' => __('legacy/index.col_leecher'),
                ])->render();
                $cache->cache_value($cacheKey, $html, $cacheTtl);
            } else {
                $html = '';
                $cache->cache_value($cacheKey, $html, $cacheTtl);
            }
        }

        return ['show' => true, 'html' => SafeHtml::fromTrustedHtml($html)];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTopUploaders(): array
    {
        if (! SiteConfig::current()->main->showTopUploader()) {
            return ['show' => false];
        }

        $allUploaders = $this->indexRepository->getTopUploaders(10);
        if ($allUploaders->isEmpty()) {
            return ['show' => false];
        }

        AssetAppender::css('.tr-top-uploader-tab>[data-table] {cursor: pointer}', 'footer', false);
        $toggleJs = <<<'JS'
document.querySelector(".tr-top-uploader-tab").addEventListener("click", function (e) {
    var td = e.target.closest("[data-table]");
    if (!td || td.classList.contains("nx-colhead")) return;
    var siblings = td.parentNode.children;
    for (var i = 0; i < siblings.length; i++) {
        siblings[i].classList.remove("nx-colhead");
    }
    td.classList.add("nx-colhead");
    var tables = document.querySelectorAll(".top-uploader");
    tables.forEach(function (t) { t.classList.add('nx-hidden'); });
    var target = document.querySelectorAll("." + td.getAttribute("data-table"));
    target.forEach(function (t) {
        t.classList.remove('nx-hidden');
        t.style.opacity = '0';
        t.style.transition = 'opacity 0.2s';
        requestAnimationFrame(function () { t.style.opacity = '1'; });
    });
})
JS;
        AssetAppender::js($toggleJs, 'footer', false);

        $recentUploaders = $this->indexRepository->getTopUploaders(10, 30);

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
            'title' => __('legacy/index.top_uploader_title'),
            'toggleHint' => __('legacy/index.top_uploader_toggle_time_range_tab'),
            'recentlyLabel' => __('legacy/index.top_uploader_toggle_time_range_recently'),
            'allLabel' => __('legacy/index.top_uploader_toggle_time_range_all'),
            'colAuthor' => __('legacy/index.col_author'),
            'colCounts' => __('legacy/index.col_counts'),
            'colRanking' => __('legacy/index.col_ranking'),
            'allRows' => $buildRows($allUploaders),
            'recentRows' => $buildRows($recentUploaders),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDisclaimer(): array
    {
        $siteName = Setting::getSiteName();

        return [
            'show' => true,
            'title' => __('legacy/index.text_disclaimer'),
            'content' => sprintf(__('legacy/index.text_disclaimer_content'), $siteName, $siteName),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBrowserNote(): array
    {
        return [
            'show' => true,
            'note' => new HtmlString((string) (__('legacy/index.text_browser_note'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildPolls(array $curUser, bool $canManage, bool $canLog, LegacyRedisCache $cache): array
    {
        $show = ! empty($curUser) && $this->globals->get('showpolls_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $pollArr = $cache->get_value('current_poll_content');
        if ($pollArr === false || $pollArr === null) {
            $pollArr = $this->indexRepository->getCurrentPoll();
            if ($pollArr) {
                $cache->cache_value('current_poll_content', $pollArr, 7226);
            }
        }

        $pollExists = ! empty($pollArr);

        $result = [
            'show' => true,
            'title' => __('legacy/index.text_polls'),
            'canManage' => $canManage,
            'newLabel' => __('legacy/index.text_new'),
            'editLabel' => __('legacy/index.text_edit'),
            'deleteLabel' => __('legacy/index.text_delete'),
            'detailLabel' => __('legacy/index.text_detail'),
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

            $uservote = $this->indexRepository->getUserVote($pollid, (int) ($curUser['id'] ?? 0));
            $result['pollId'] = $pollid;
            $result['question'] = $question;
            $result['options'] = $options;
            $result['hasVoted'] = $uservote !== null;
            $result['blankVoteLabel'] = __('legacy/index.radio_blank_vote');
            $result['submitVoteLabel'] = __('legacy/index.submit_vote');
            $result['canLog'] = $canLog;
            $result['previousPollsLabel'] = __('legacy/index.text_previous_polls');
            $result['votesLabel'] = __('legacy/index.text_votes');

            if ($uservote !== null) {
                $results = $cache->get_value('current_poll_result');
                if ($results === false || $results === null) {
                    $results = $this->indexRepository->getPollResults($pollid);
                    $cache->cache_value('current_poll_result', $results, 3652);
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
     * @return array<string, mixed>
     */
    private function buildStats(LegacyRedisCache $cache): array
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
    private function buildTrackerLoad(): array
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

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function appendAssets(array $curUser): void
    {
        $toastLang = json_encode([
            'newMessage' => __('legacy/index.toast_new_message'),
            'shoutboxMention' => __('legacy/index.toast_shoutbox_mention'),
            'from' => __('legacy/index.toast_from'),
            'close' => __('legacy/index.toast_close'),
            'userId' => (int) ($curUser['id'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        AssetAppender::css('styles/shoutbox.css', 'header', true);
        AssetAppender::js('js/shoutbox.js', 'footer', true);
        AssetAppender::js("window.TOAST_LANG = $toastLang;", 'footer', false, 'toast-lang');
        AssetAppender::css('styles/toast.css', 'header', true);
        AssetAppender::js('js/toast.js', 'footer', true);
    }
}
