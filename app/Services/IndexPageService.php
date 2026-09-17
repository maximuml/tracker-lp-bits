<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\IndexRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CoverThumb;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Shoutbox;
use App\Support\UserDisplay;
use App\ViewModels\IndexPageViewModel;

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
        private readonly IndexStatsSectionBuilder $stats,
        private readonly IndexPollSectionBuilder $polls,
        private readonly IndexMetaSectionBuilder $meta,
        ?CoverThumb $coverThumb = null,
    ) {
        $this->coverThumb = $coverThumb ?? new CoverThumb($this->cache);
    }

    public function build(): IndexPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $lang = (array) trans('legacy/index');

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'canNewsManage' => Permission::can(PermissionEnum::NEWS_MANAGE),
            'canPollManage' => Permission::can(PermissionEnum::POLL_MANAGE),
            'canSbManage' => Permission::can(PermissionEnum::SB_MANAGE),
            'canLog' => Permission::can(PermissionEnum::LOG),
        ];

        // News
        $data['news'] = $this->buildNews($lang, $data['canNewsManage'], $this->cache);

        // Shoutbox
        $data['shoutbox'] = $this->buildShoutbox($lang, $data['canSbManage'], (int) ($curUser['id'] ?? 0));

        $data['extraModules'] = '';

        // Latest forum posts
        $data['forumPosts'] = $this->buildForumPosts($lang, $curUser);

        // Latest torrents
        $data['latestTorrents'] = $this->buildLatestTorrents($lang, $this->cache);

        // Top uploaders
        $data['topUploaders'] = $this->meta->buildTopUploaders($lang);

        // Polls
        $data['polls'] = $this->polls->buildPolls($lang, $curUser, $data['canPollManage'], $data['canLog'], $this->cache);

        // Stats
        $data['stats'] = $this->stats->buildStats($lang, $this->cache);

        // Tracker load
        $data['trackerLoad'] = $this->stats->buildTrackerLoad($lang);

        // Disclaimer
        $data['disclaimer'] = $this->meta->buildDisclaimer($lang);

        // Browser note
        $data['browserNote'] = $this->meta->buildBrowserNote($lang);

        // Reset unread news count
        if (! empty($curUser['id'])) {
            $this->cache->delete_value('user_'.(int) $curUser['id'].'_unread_news_count');
        }

        return new IndexPageViewModel(
            lang: $data['lang'],
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
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildNews(array $lang, bool $canManage, LegacyRedisCache $cache): array
    {
        $maxNews = (int) $this->globals->get('maxnewsnum_main', 0);

        return [
            'show' => true,
            'title' => $lang['text_recent_news'] ?? 'Recent news',
            'canManage' => $canManage,
            'manageLink' => $lang['text_news_page'] ?? 'News page',
            'items' => $this->indexRepository->getLatestNews($maxNews),
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
        $show = $this->globals->get('showshoutbox_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $csrf = Shoutbox::csrfToken($userId);
        AssetAppender::js("var SHOUT_CSRF = '".addslashes($csrf)."';", 'footer', false);

        $clearJs = '';
        if ($canManage) {
            $clearJs = <<<JS
document.getElementById('clear-shout-box').addEventListener("click", function () {
    layer.confirm("{$lang['sure_to_clear_shout_box']}", {title: "Info", btn: ['Yes', "Cancel"], btnAlign: 'c'}, function (layerIndex) {
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
        $show = $this->globals->get('showlastxforumposts_main', '') === 'yes' && ! empty($curUser);

        if (! $show) {
            return ['show' => false];
        }

        $posts = $this->indexRepository->getLatestForumPosts(5, (int) UserDisplay::currentClass());

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
    private function buildLatestTorrents(array $lang, LegacyRedisCache $cache): array
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
                        'ownerHtml' => $ownerHtml,
                        'name' => (string) $torrent->name,
                        'nameShort' => mb_substr((string) $torrent->name, 0, 60),
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
                $cache->cache_value($cacheKey, $html, $cacheTtl);
            } else {
                $html = '';
                $cache->cache_value($cacheKey, $html, $cacheTtl);
            }
        }

        return ['show' => true, 'html' => $html];
    }
}
