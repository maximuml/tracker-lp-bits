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
        $data['topUploaders'] = $this->meta->buildTopUploaders();

        // Polls
        $data['polls'] = $this->polls->buildPolls($curUser, $data['canPollManage'], $data['canLog'], $this->cache);

        // Stats
        $data['stats'] = $this->stats->buildStats($this->cache);

        // Tracker load
        $data['trackerLoad'] = $this->stats->buildTrackerLoad();

        // Disclaimer
        $data['disclaimer'] = $this->meta->buildDisclaimer();

        // Browser note
        $data['browserNote'] = $this->meta->buildBrowserNote();

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
            'toolbar' => Shoutbox::toolbar('shbox', 'shbox_text'),
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

        return ['show' => true, 'html' => $html];
    }
}
