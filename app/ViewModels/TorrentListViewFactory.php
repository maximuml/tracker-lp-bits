<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\UserAppendPromotion;
use App\Enums\UserTimeType;
use App\Models\Torrent;
use App\Repositories\TorrentModerationRepository;
use App\Services\TorrentStatsService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Html\Tag;
use App\Support\Input;
use App\Support\Palette;
use App\Support\Promotion;
use App\Support\Ratio;
use App\Support\SearchBox;
use App\Support\Time;
use App\Support\Torrent\TorrentStatus;
use App\Support\TorrentAccess;
use App\Support\TorrentBookmark;
use App\Support\UserDisplay;

/**
 * Builds TorrentListViewModel for the modern torrents table.
 *
 * Faithful port of the data assembly previously embedded in
 * `TorrentTable::render()` — same queries, same caches, same
 * permission/wait/ratio logic — but returns structured data instead of
 * echoing markup. The Blade template owns the table skeleton.
 */
final class TorrentListViewFactory
{
    private const MAX_NAME_LENGTH = 200;

    public function __construct(
        private readonly LegacyRedisCache $cache,
        private readonly CurrentUser $currentUser,
        private readonly TorrentRepositoryInterface $torrentRep,
        private readonly TorrentModerationRepository $moderationRep,
        private readonly TorrentStatsService $statsService,
        private readonly TagRepositoryInterface $tagRep,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function create(array $rows, int $searchBoxId): TorrentListViewModel
    {
        $cache = $this->cache;
        $user = $this->currentUser->get() ?? [];
        $config = SiteConfig::current();
        $waitsystem = $config->main->waitSystem(false) ? 'yes' : 'no';
        $enableTooltip = $config->tweak->enableTooltip(false);

        $torrent = new TorrentStatus;
        $torrentIdArr = $ownerIdArr = [];
        foreach ($rows as $row) {
            $torrentIdArr[] = $row['id'];
            $ownerIdArr[] = $row['owner'];
        }
        UserDisplay::preload($ownerIdArr);

        $seedingStatus = $torrent->listLeechingSeedingStatus($user['id'], $torrentIdArr);
        $tagResult = $this->statsService->getTorrentTagsGrouped($torrentIdArr);

        $showCover = false;
        if ($searchBoxId) {
            $searchBoxExtra = SearchBox::value($cache, $searchBoxId, 'extra');
            if (! empty($searchBoxExtra[\App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST])) {
                $showCover = true;
            }
        }

        $lastBrowse = min((int) $user['last_browse'], TIMENOW);
        $wait = 0;
        if (UserDisplay::currentClass() < UC_VIP && $waitsystem === 'yes') {
            $ratio = Ratio::forUserId($user['id'], false);
            $gigs = $user['uploaded'] / (1024 * 1024 * 1024);
            if ($gigs > 10) {
                if ($ratio < 0.4) {
                    $wait = 24;
                } elseif ($ratio < 0.5) {
                    $wait = 12;
                } elseif ($ratio < 0.6) {
                    $wait = 6;
                } elseif ($ratio < 0.8) {
                    $wait = 3;
                }
            }
        }

        $showComments = (bool) ($user['showcomnum'] ?? false);
        $canManage = Permission::canManageTorrent();
        // Columns are only rendered alongside rows; skip the header build
        // (and its lang lookups) for empty listings.
        $columns = $rows === []
            ? []
            : $this->columns($wait > 0, $showComments, $canManage, (string) ($user['timetype'] ?? ''));

        $caticonrow = Category::iconRowWithContext($user['caticon']);
        $hasSecondIcon = is_array($caticonrow) && (bool) ($caticonrow['secondicon'] ?? false);

        $posStates = $user['appendsticky'] ? Torrent::listPosStates() : [];
        $appendNew = (bool) ($user['appendnew'] ?? false);
        $showLastCom = $enableTooltip && ($user['showlastcom'] ?? false);
        $timeAlive = ($user['timetype'] ?? null) == UserTimeType::TIMEALIVE->value;
        $promotionNote = ($user['appendpromotion'] ?? null) == UserAppendPromotion::HIGHLIGHT->value;
        $canViewAnonymous = Permission::canViewAnonymous();
        $canDelete = Permission::canDeleteTorrent();
        $returnTo = rawurlencode(Input::serverValue('REQUEST_URI', ''));

        $outRows = [];
        $lastcomTooltip = [];
        $counter = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $highlight = Promotion::backgroundStyleWithContext((int) $row['sp_state'], (string) $row['pos_state'], $row);

            $categoryCell = '-';
            if (isset($row['category'])) {
                $categoryCell = Category::imageTagWithContext($row['category'], '?');
                if ($hasSecondIcon) {
                    $categoryCell .= Category::secondIconWithContext($row);
                }
            }

            $nameTitle = trim((string) $row['name']);
            $dispname = $nameTitle;
            if (mb_strlen($dispname, 'UTF-8') > self::MAX_NAME_LENGTH) {
                $dispname = mb_substr($dispname, 0, self::MAX_NAME_LENGTH - 2, 'UTF-8').'..';
            }

            $stickyCount = 0;
            $stickyTitle = '';
            if ($user['appendsticky']) {
                $posState = $posStates[$row['pos_state']] ?? ['text' => '', 'icon_counts' => 0];
                $stickyCount = (int) ($posState['icon_counts'] ?? 0);
                $stickyTitle = (string) $posState['text'];
            }

            $badges = $this->torrentRep->getPaidIcon($row)
                .Promotion::appendWithContext($row['sp_state'], '', true, $row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false)
                .Promotion::appendSubWithContext($row['sp_state'], '', true, $row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false)
                .TorrentAccess::hrImage($row, $row['search_box_id'])
                .$this->moderationRep->renderApprovalStatus($row['approval_status']);

            $tagOwns = $tagResult->get($id);
            $tags = $tagOwns
                ? $this->tagRep->renderSpan($row['search_box_id'], $tagOwns->pluck('tag_id')->toArray())
                : '';

            $progressBar = isset($seedingStatus[$id])
                ? $torrent->renderProgressBar($seedingStatus[$id]['active_status'], $seedingStatus[$id]['progress'])
                : '';

            $showDownload = (bool) ($user['dlicon'] ?? false) && (bool) ($user['downloadpos'] ?? true);
            $showBookmark = (bool) ($user['bmicon'] ?? false);
            $bookmarkMarkup = $showBookmark
                ? TorrentBookmark::stateMarkupWithContext($user['id'], $id)
                : '';

            $waitText = null;
            $waitColor = null;
            if ($wait) {
                $elapsed = floor((TIMENOW - strtotime((string) $row['added'])) / 3600);
                if ($elapsed < $wait) {
                    $waitColor = dechex((int) (floor(127 * ($wait - $elapsed) / 48 + 128) * 65536));
                    $waitText = number_format($wait - $elapsed).__('legacy/functions.text_h');
                } else {
                    $waitText = (string) __('legacy/functions.text_none');
                }
            }

            $commentIsNew = false;
            $tooltipId = null;
            if ($showComments && $row['comments'] && $showLastCom) {
                $lastcom = $cache->get_value('torrent_'.$id.'_last_comment_content');
                if (! $lastcom) {
                    $lastcom = $this->statsService->getLastComment($id);
                    $cache->cache_value('torrent_'.$id.'_last_comment_content', $lastcom, 1855);
                }
                if ($lastcom) {
                    $commentIsNew = $lastcom['user'] != $user['id'] && strtotime($lastcom['added']) >= $lastBrowse;
                    $lastcomtime = $timeAlive
                        ? __('legacy/functions.text_blank').Time::format($lastcom['added'], true, false, true)
                        : __('legacy/functions.text_at_time').$lastcom['added'];
                    $tooltipId = 'lastcom_'.$counter;
                    $lastcomTooltip[] = [
                        'id' => $tooltipId,
                        'content' => ($commentIsNew ? "<b>(<font class='new'>".__('legacy/functions.text_new_uppercase').'</font>)</b> ' : '')
                            .__('legacy/functions.text_last_commented_by').UserDisplay::username($lastcom['user']).$lastcomtime.'<br />'
                            .Format::formatComment(mb_substr($lastcom['text'], 0, 100, 'UTF-8').(mb_strlen($lastcom['text'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false),
                    ];
                }
            }

            if ($row['seeders']) {
                $seedRatio = $row['leechers'] ? $row['seeders'] / $row['leechers'] : 1;
                $seedersColor = Ratio::seedLeechColor($seedRatio) ?: null;
                $seedersUrl = 'details.php?id='.$id.'&hit=1&dllist=1#seeders';
                $seedersZeroClass = '';
            } else {
                $seedersColor = null;
                $seedersUrl = null;
                $seedersZeroClass = Palette::seederLink(0);
            }

            $leechersUrl = $row['leechers'] ? 'details.php?id='.$id.'&hit=1&dllist=1#leechers' : null;
            $snatchedUrl = $row['times_completed'] >= 1 ? 'viewsnatches.php?id='.$id : null;

            $uploaderAnonymous = $row['anonymous'] == 1;
            $uploaderShowOwner = $uploaderAnonymous
                && ($canViewAnonymous || (isset($row['owner']) && $row['owner'] == $user['id']));
            $uploaderName = isset($row['owner'])
                ? SafeHtml::fromTrustedHtml(UserDisplay::username($row['owner']))
                : null;

            $staffDeleteUrl = ($canManage && $canDelete) ? 'fastdelete.php?id='.$id : null;
            $staffEditUrl = $canManage ? 'edit.php?returnto='.$returnTo.'&id='.$id : null;

            $outRows[] = new TorrentListRow(
                id: $id,
                rowAttrs: SafeHtml::fromTrustedHtml($highlight),
                categoryCell: SafeHtml::fromTrustedHtml($categoryCell),
                coverSrc: $showCover ? (string) ($row['cover'] ?? '') : null,
                stickyCount: $stickyCount,
                stickyTitle: $stickyTitle,
                nameUrl: 'details.php?id='.$id.'&hit=1',
                displayName: $dispname,
                nameTitle: $nameTitle,
                isNew: $appendNew && strtotime((string) $row['added']) >= $lastBrowse,
                isBanned: $row['banned'] == 1,
                badges: SafeHtml::fromTrustedHtml($badges),
                tags: SafeHtml::fromTrustedHtml($tags),
                progressBar: SafeHtml::fromTrustedHtml($progressBar),
                showDownload: $showDownload,
                downloadUrl: 'download.php?id='.$id,
                showBookmark: $showBookmark,
                bookmarkElementId: 'bookmark'.$counter,
                bookmarkCounter: $counter,
                bookmarkMarkup: SafeHtml::fromTrustedHtml($bookmarkMarkup),
                waitText: $waitText,
                waitColor: $waitColor,
                commentsUrl: 'details.php?id='.$id.'&hit=1&cmtpage=1#startcomments',
                comments: (int) $row['comments'],
                commentIsNew: $commentIsNew,
                lastCommentTooltipId: $tooltipId,
                time: SafeHtml::fromTrustedHtml((string) Time::format((string) $row['added'], false, true)),
                size: SafeHtml::fromTrustedHtml(Format::sizeCompact((float) $row['size'])),
                seedersUrl: $seedersUrl,
                seeders: (int) $row['seeders'],
                seedersColor: $seedersColor,
                seedersZeroClass: $seedersZeroClass,
                leechersUrl: $leechersUrl,
                leechers: (int) $row['leechers'],
                snatchedUrl: $snatchedUrl,
                snatched: (int) $row['times_completed'],
                uploaderAnonymous: $uploaderAnonymous,
                uploaderShowOwner: $uploaderShowOwner,
                uploaderName: $uploaderName,
                staffDeleteUrl: $staffDeleteUrl,
                staffEditUrl: $staffEditUrl,
            );
            $counter++;
        }

        $lastcomTooltips = ($enableTooltip && (empty($user) || ($user['showlastcom'] ?? false)))
            ? Tag::tooltipContainer($lastcomTooltip, 400)
            : '';

        // The legacy renderer also emitted a second (always empty) tooltip
        // container — dropped here; $torrent_tooltip was never populated.
        return new TorrentListViewModel(
            columns: $columns,
            rows: $outRows,
            showComments: $showComments,
            canManage: $canManage,
            showPromotionNote: $promotionNote,
            lastCommentTooltips: SafeHtml::fromTrustedHtml($lastcomTooltips),
        );
    }

    /**
     * Column descriptors for the table head. Sort links preserve the
     * current query minus sort/type, same as the legacy renderer.
     *
     * @return list<array{key: string, label: string, iconClass: string, iconTitle: string, sortUrl: ?string}>
     */
    private function columns(bool $showWait, bool $showComments, bool $canManage, string $timetype): array
    {
        $queryParams = [];
        foreach (request()->query() as $getName => $getValue) {
            if (is_array($getValue) || in_array($getName, ['sort', 'type'], true)) {
                continue;
            }
            if ($getName === 'page') {
                $getValue = (string) max(0, (int) $getValue);
            }
            $queryParams[(string) $getName] = (string) $getValue;
        }
        $oldlink = $queryParams ? http_build_query($queryParams, '', '&').'&' : '';
        $sort = request()->query('sort', '');
        $desc = request()->query('type') == 'desc';

        $sortUrl = static function (int $i) use ($oldlink, $sort, $desc): string {
            $type = ((string) $sort === (string) $i) ? ($desc ? 'asc' : 'desc') : ($i == 1 ? 'asc' : 'desc');

            // Raw '&' here — the template escapes it to '&amp;' via {{ }}.
            return '?'.$oldlink.'sort='.$i.'&type='.$type;
        };

        $columns = [
            ['key' => 'type', 'label' => (string) __('legacy/functions.col_type'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => null],
            ['key' => 'name', 'label' => (string) __('legacy/functions.col_name'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => $sortUrl(1)],
        ];
        if ($showWait) {
            $columns[] = ['key' => 'wait', 'label' => (string) __('legacy/functions.col_wait'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => null];
        }
        if ($showComments) {
            $columns[] = ['key' => 'comments', 'label' => '', 'iconClass' => 'comments', 'iconTitle' => (string) __('legacy/functions.title_number_of_comments'), 'sortUrl' => $sortUrl(3)];
        }
        $columns[] = ['key' => 'time', 'label' => '', 'iconClass' => 'time', 'iconTitle' => $timetype != UserTimeType::TIMEALIVE->value ? (string) __('legacy/functions.title_time_added') : (string) __('legacy/functions.title_time_alive'), 'sortUrl' => $sortUrl(4)];
        $columns[] = ['key' => 'size', 'label' => '', 'iconClass' => 'size', 'iconTitle' => (string) __('legacy/functions.title_size'), 'sortUrl' => $sortUrl(5)];
        $columns[] = ['key' => 'seeders', 'label' => '', 'iconClass' => 'seeders', 'iconTitle' => (string) __('legacy/functions.title_number_of_seeders'), 'sortUrl' => $sortUrl(7)];
        $columns[] = ['key' => 'leechers', 'label' => '', 'iconClass' => 'leechers', 'iconTitle' => (string) __('legacy/functions.title_number_of_leechers'), 'sortUrl' => $sortUrl(8)];
        $columns[] = ['key' => 'snatched', 'label' => '', 'iconClass' => 'snatched', 'iconTitle' => (string) __('legacy/functions.title_number_of_snatched'), 'sortUrl' => $sortUrl(6)];
        $columns[] = ['key' => 'uploader', 'label' => (string) __('legacy/functions.col_uploader'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => $sortUrl(9)];
        if ($canManage) {
            $columns[] = ['key' => 'action', 'label' => (string) __('legacy/functions.col_action'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => null];
        }

        return $columns;
    }
}
