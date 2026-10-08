<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Repositories\IndexRepository;
use App\Support\AssetAppender;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CoverThumb;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Ratio;
use App\Support\Shoutbox;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\ViewModels\Index\IndexBrowserNoteSection;
use App\ViewModels\Index\IndexClassStatRow;
use App\ViewModels\Index\IndexDisclaimerSection;
use App\ViewModels\Index\IndexForumPostItem;
use App\ViewModels\Index\IndexForumPostsSection;
use App\ViewModels\Index\IndexLatestTorrentsSection;
use App\ViewModels\Index\IndexNewsItem;
use App\ViewModels\Index\IndexNewsSection;
use App\ViewModels\Index\IndexPollsSectionFactory;
use App\ViewModels\Index\IndexShoutboxSection;
use App\ViewModels\Index\IndexStatsSection;
use App\ViewModels\Index\IndexTodayUserCard;
use App\ViewModels\Index\IndexTodayUsers;
use App\ViewModels\Index\IndexTopUploaderRow;
use App\ViewModels\Index\IndexTopUploadersSection;
use App\ViewModels\IndexPageViewModel;
use Carbon\Carbon;

/**
 * Prepares section data for the index page, replacing the legacy
 * index_content.php partial with typed Blade-rendered sections.
 * Each section is a typed ViewModel built by a private builder here.
 */
final class IndexPageService
{
    private readonly CoverThumb $coverThumb;

    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly NexusCache $cache,
        private readonly IndexRepository $indexRepository,
        private readonly IndexPollsSectionFactory $indexPollsSectionFactory,
        ?CoverThumb $coverThumb = null,
    ) {
        $this->coverThumb = $coverThumb ?? new CoverThumb($this->cache);
    }

    public function build(): IndexPageViewModel
    {
        $curUser = $this->currentUser;

        $canNewsManage = Permission::can(PermissionEnum::NEWS_MANAGE);
        $canPollManage = Permission::can(PermissionEnum::POLL_MANAGE);
        $canSbManage = Permission::can(PermissionEnum::SB_MANAGE);
        $canLog = Permission::can(PermissionEnum::LOG);

        $news = $this->buildNews($canNewsManage);
        $shoutbox = $this->buildShoutbox($canSbManage, $curUser);
        $forumPosts = $this->buildForumPosts($curUser);
        $latestTorrents = $this->buildLatestTorrents();
        $topUploaders = $this->buildTopUploaders();
        $polls = $this->indexPollsSectionFactory->build($curUser->get() ?? [], $canPollManage, $canLog);
        $stats = $this->buildStats();
        $disclaimer = $this->buildDisclaimer();
        $browserNote = $this->buildBrowserNote();

        if ($shoutbox->canManage || $topUploaders->show) {
            AssetAppender::js('js/index-sections.js', 'footer', true);
        }

        // Reset unread news count
        if ($curUser->isLoggedIn()) {
            $this->cache->forget('user_'.$curUser->id().'_unread_news_count');
        }

        return new IndexPageViewModel(
            curUser: $curUser->get() ?? [],
            canNewsManage: $canNewsManage,
            canPollManage: $canPollManage,
            canSbManage: $canSbManage,
            canLog: $canLog,
            news: $news,
            shoutbox: $shoutbox,
            extraModules: '',
            forumPosts: $forumPosts,
            latestTorrents: $latestTorrents,
            topUploaders: $topUploaders,
            polls: $polls,
            stats: $stats,
            disclaimer: $disclaimer,
            browserNote: $browserNote,
        );
    }

    private function buildNews(bool $canManage): IndexNewsSection
    {
        $maxNews = SiteConfig::current()->main->maxNewsNum(0);
        $items = array_values(array_map(
            fn (array $row) => IndexNewsItem::fromRow($row),
            $this->indexRepository->getLatestNews($maxNews),
        ));

        return new IndexNewsSection(
            show: true,
            title: __('index.text_recent_news'),
            canManage: $canManage,
            manageLink: __('index.text_news_page'),
            items: $items,
            showHideTitle: __('index.title_show_or_hide'),
            editLabel: __('index.text_e'),
            deleteLabel: __('index.text_d'),
        );
    }

    private function buildShoutbox(bool $canManage, CurrentUser $curUser): IndexShoutboxSection
    {
        $show = SiteConfig::current()->main->showShoutbox();

        if (! $show) {
            return new IndexShoutboxSection;
        }

        $csrf = Shoutbox::csrfToken($curUser->id());
        AssetAppender::js("var SHOUT_CSRF = '".addslashes($csrf)."';", 'footer', false);

        return new IndexShoutboxSection(
            show: true,
            title: __('index.text_shoutbox'),
            autoRefreshLabel: __('index.text_auto_refresh_after'),
            secondsLabel: __('index.text_seconds'),
            historyLabel: __('index.text_shoutbox_history'),
            canManage: $canManage,
            clearLabel: __('index.clear_shout_box'),
            clearConfirm: __('index.sure_to_clear_shout_box'),
            toolbar: SafeHtml::fromTrustedHtml(Shoutbox::toolbar('shbox', 'shbox_text')),
            messageLabel: __('index.text_message'),
            submitLabel: __('index.sumbit_shout'),
            clearButtonLabel: __('index.submit_clear'),
            showHideTitle: __('index.title_show_or_hide'),
            refreshSeconds: (int) $curUser->value('sbrefresh', 120),
        );
    }

    private function buildForumPosts(CurrentUser $curUser): IndexForumPostsSection
    {
        $show = SiteConfig::current()->main->showLastXForumPosts() && $curUser->isLoggedIn();

        if (! $show) {
            return new IndexForumPostsSection;
        }

        $posts = $this->indexRepository->getLatestForumPosts(5, (int) UserDisplay::currentClass());
        UserDisplay::preload(collect($posts)->map(fn ($p) => (int) ($p['userpost'] ?? 0))->all());

        return new IndexForumPostsSection(
            show: count($posts) > 0,
            title: __('index.text_last_five_posts'),
            colTopicTitle: __('index.col_topic_title'),
            colView: __('index.col_view'),
            colAuthor: __('index.col_author'),
            colPostedAt: __('index.col_posted_at'),
            textIn: __('index.text_in'),
            items: array_values(array_map(fn (array $row) => IndexForumPostItem::fromRow($row), $posts)),
        );
    }

    private function buildLatestTorrents(): IndexLatestTorrentsSection
    {
        $show = SiteConfig::current()->main->showLastXTorrents();

        if (! $show) {
            return new IndexLatestTorrentsSection;
        }

        $cacheKey = Locale::currentLangDir('en').'_index_latest_torrents_grid_v3';
        $cacheTtl = 120;
        $html = $this->cache->get($cacheKey);

        if ($html === false || $html === null || $html === '') {
            $torrents = $this->indexRepository->getLatestTorrents(12);
            if ($torrents->isNotEmpty()) {
                UserDisplay::preload($torrents->map(fn ($t) => (int) $t->owner)->all());
                $items = [];
                foreach ($torrents as $torrent) {
                    $detailsUrl = '/web/details/'.(int) $torrent->id.'?hit=1';
                    $rawCover = trim((string) ($torrent->cover ?? ''));
                    $thumbUrl = $rawCover !== '' ? $this->coverThumb->urlWithContext((string) $rawCover, (int) 240, (int) 360, (int) 82) : '';
                    $typeLabel = trim((string) ($torrent->basic_category->name ?? ''));
                    $items[] = [
                        'detailsUrl' => $detailsUrl,
                        'thumbUrl' => $thumbUrl,
                        'typeLabel' => $typeLabel,
                        'owner' => $torrent->anonymous ? null : UserDisplay::username((int) $torrent->owner),
                        'name' => (string) $torrent->name,
                        'nameShort' => mb_substr((string) $torrent->name, 0, 60),
                        'seeders' => (int) $torrent->seeders,
                        'leechers' => (int) $torrent->leechers,
                        'size' => Format::size((int) $torrent->size),
                    ];
                }
                $html = view('index.sections.latest_torrents', [
                    'items' => $items,
                    'title' => __('index.text_latest_torrents'),
                    'colSeeder' => __('index.col_seeder'),
                    'colLeecher' => __('index.col_leecher'),
                ])->render();
                $this->cache->put($cacheKey, $html, $cacheTtl);
            } else {
                $html = '';
                $this->cache->put($cacheKey, $html, $cacheTtl);
            }
        }

        return new IndexLatestTorrentsSection(show: true, html: SafeHtml::fromTrustedHtml($html));
    }

    private function buildTopUploaders(): IndexTopUploadersSection
    {
        if (! SiteConfig::current()->main->showTopUploader()) {
            return new IndexTopUploadersSection;
        }

        $allUploaders = $this->indexRepository->getTopUploaders(10);
        if ($allUploaders->isEmpty()) {
            return new IndexTopUploadersSection;
        }

        $recentUploaders = $this->indexRepository->getTopUploaders(10, 30);

        $buildRows = function ($uploaders): array {
            $rows = [];
            foreach ($uploaders as $ranking => $uploader) {
                $rows[] = new IndexTopUploaderRow(
                    username: UserDisplay::username($uploader->id),
                    count: (int) $uploader->count,
                    rank: $ranking + 1,
                );
            }

            return $rows;
        };

        return new IndexTopUploadersSection(
            show: true,
            title: __('index.top_uploader_title'),
            toggleHint: __('index.top_uploader_toggle_time_range_tab'),
            recentlyLabel: __('index.top_uploader_toggle_time_range_recently'),
            allLabel: __('index.top_uploader_toggle_time_range_all'),
            colAuthor: __('index.col_author'),
            colCounts: __('index.col_counts'),
            colRanking: __('index.col_ranking'),
            allRows: $buildRows($allUploaders),
            recentRows: $buildRows($recentUploaders),
        );
    }

    private function buildDisclaimer(): IndexDisclaimerSection
    {
        $siteName = Setting::getSiteName();

        return new IndexDisclaimerSection(
            show: true,
            title: __('index.text_disclaimer'),
            content: sprintf(__('index.text_disclaimer_content'), $siteName, $siteName),
        );
    }

    private function buildBrowserNote(): IndexBrowserNoteSection
    {
        return new IndexBrowserNoteSection(
            show: true,
        );
    }

    private function buildStats(): IndexStatsSection
    {
        $show = SiteConfig::current()->main->showStats();

        if (! $show) {
            return new IndexStatsSection;
        }

        AssetAppender::js('js/stats-details.js', 'footer', true);

        $userStats = $this->indexRepository->getUserStats();
        $torrentStats = $this->indexRepository->getTorrentStats();
        $classStats = $this->indexRepository->getClassStats();
        $todayUsers = $this->indexRepository->getTodayActiveUsers();
        UserDisplay::preload($todayUsers['ids']);
        $cards = [];
        foreach ($todayUsers['ids'] as $uid) {
            $row = UserDisplay::row($uid);
            if ($row === false) {
                continue;
            }
            $ratio = Ratio::userRatioNumeric((float) $row['uploaded'], (float) $row['downloaded']);
            $cards[(int) $uid] = new IndexTodayUserCard(
                username: (string) $row['username'],
                classLabel: UserClass::name((int) $row['class'], false, false, true),
                ratio: number_format((float) $ratio, 3),
                uploaded: Format::size((float) $row['uploaded']),
                downloaded: Format::size((float) $row['downloaded']),
                lastSeen: Carbon::parse($row['last_access'])->format('H:i'),
                avatar: (string) ($row['avatar'] ?? ''),
            );
        }
        $maxusers = SiteConfig::current()->main->maxUsers();

        return new IndexStatsSection(
            show: true,
            title: __('index.text_tracker_statistics'),
            userStats: [
                'activeToday' => number_format($userStats['totalonlinetoday']),
                'activeThisWeek' => number_format($userStats['totalonlineweek']),
                'registered' => number_format($userStats['registered']).' / '.number_format($maxusers),
                'unconfirmed' => number_format($userStats['unverified']),
                'vip' => number_format($userStats['vip']),
                'vipLabel' => UserClass::name(UC_VIP, false, false, true),
                'donors' => number_format($userStats['donated']),
                'donorsLabel' => __('index.row_donors'),
                'warned' => number_format($userStats['warned']),
                'warnedLabel' => __('index.row_warned_users'),
                'banned' => number_format($userStats['disabled']),
                'bannedLabel' => __('index.row_banned_users'),
                'male' => number_format($userStats['registered_male']),
                'maleLabel' => __('index.row_male_users'),
                'female' => number_format($userStats['registered_female']),
                'femaleLabel' => __('index.row_female_users'),
            ],
            torrentStats: [
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
            classStats: [
                new IndexClassStatRow(UserClass::name(UC_PEASANT, false, false, true), number_format($classStats[UC_PEASANT]), 'leechwarned'),
                new IndexClassStatRow(UserClass::name(UC_USER, false, false, true), number_format($classStats[UC_USER])),
                new IndexClassStatRow(UserClass::name(UC_POWER_USER, false, false, true), number_format($classStats[UC_POWER_USER])),
                new IndexClassStatRow(UserClass::name(UC_ELITE_USER, false, false, true), number_format($classStats[UC_ELITE_USER])),
                new IndexClassStatRow(UserClass::name(UC_CRAZY_USER, false, false, true), number_format($classStats[UC_CRAZY_USER])),
                new IndexClassStatRow(UserClass::name(UC_INSANE_USER, false, false, true), number_format($classStats[UC_INSANE_USER])),
                new IndexClassStatRow(UserClass::name(UC_VETERAN_USER, false, false, true), number_format($classStats[UC_VETERAN_USER])),
                new IndexClassStatRow(UserClass::name(UC_EXTREME_USER, false, false, true), number_format($classStats[UC_EXTREME_USER])),
                new IndexClassStatRow(UserClass::name(UC_ULTIMATE_USER, false, false, true), number_format($classStats[UC_ULTIMATE_USER])),
                new IndexClassStatRow(UserClass::name(UC_NEXUS_MASTER, false, false, true), number_format($classStats[UC_NEXUS_MASTER])),
            ],
            todayUsers: new IndexTodayUsers(
                count: (int) ($todayUsers['count'] ?? 0),
                cards: $cards,
            ),
            labels: [
                'rowUsersActiveToday' => __('index.row_users_active_today'),
                'rowUsersActiveThisWeek' => __('index.row_users_active_this_week'),
                'rowRegisteredUsers' => __('index.row_registered_users'),
                'rowUnconfirmedUsers' => __('index.row_unconfirmed_users'),
                'rowTorrents' => __('index.row_torrents'),
                'rowDeadTorrents' => __('index.row_dead_torrents'),
                'rowSeeders' => __('index.row_seeders'),
                'rowLeechers' => __('index.row_leechers'),
                'rowPeers' => __('index.row_peers'),
                'rowSeederLeecherRatio' => __('index.row_seeder_leecher_ratio'),
                'rowActiveBrowsingUsers' => __('index.row_active_browsing_users'),
                'rowTrackerActiveUsers' => __('index.row_tracker_active_users'),
                'rowTotalSizeOfTorrents' => __('index.row_total_size_of_torrents'),
                'rowTotalUploaded' => __('index.row_total_uploaded'),
                'rowTotalDownloaded' => __('index.row_total_downloaded'),
                'rowTotalData' => __('index.row_total_data'),
            ],
        );
    }

    public function appendAssets(CurrentUser $curUser): void
    {
        AssetAppender::css('styles/shoutbox.css', 'header', true);
        $shoutLang = json_encode([
            'requestFailed' => __('shoutbox.js_request_failed'),
            'invalidResponse' => __('shoutbox.js_invalid_response'),
            'spoilerTitle' => __('shoutbox.js_spoiler_title'),
            'quoteAuthor' => __('shoutbox.js_quote_author'),
            'url' => __('shoutbox.js_url'),
            'linkText' => __('shoutbox.js_link_text'),
            'confirmDelete' => __('shoutbox.js_confirm_delete'),
            'collapse' => __('shoutbox.js_collapse'),
            'expand' => __('shoutbox.js_expand'),
            'newMentions' => __('shoutbox.js_new_mentions'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        AssetAppender::js("window.SHOUT_LANG = $shoutLang;", 'footer', false, 'shout-lang');
        AssetAppender::js('js/shoutbox.js', 'footer', true);
    }
}
