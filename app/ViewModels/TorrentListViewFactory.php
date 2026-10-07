<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
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
use App\Support\Locale;
use App\Support\Palette;
use App\Support\Promotion;
use App\Support\Ratio;
use App\Support\SearchBox;
use App\Support\Time;
use App\Support\Torrent\TorrentStatus;
use App\Support\TorrentAccess;
use App\Support\UserDisplay;
use App\ViewModels\Torrent\ApprovalBadge;
use App\ViewModels\Torrent\CategoryIcon;
use App\ViewModels\Torrent\TorrentBadgeSet;
use App\ViewModels\Torrent\TorrentProgress;

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
        private readonly ?LegacyRedisCache $cache,
        private readonly CurrentUser $currentUser,
        private readonly TorrentModerationRepository $moderationRep,
        private readonly TorrentStatsService $statsService,
        private readonly TagRepositoryInterface $tagRep,
        private readonly TorrentStatus $torrentStatus,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function create(array $rows, int $searchBoxId): TorrentListViewModel
    {
        $cache = $this->cache;
        if ($cache === null) {
            throw new \RuntimeException('Cache not initialized');
        }
        $user = $this->currentUser->get() ?? [];
        $config = SiteConfig::current();
        $waitsystem = $config->main->waitSystem(false) ? 'yes' : 'no';
        $enableTooltip = $config->tweak->enableTooltip(false);

        $torrentIdArr = $ownerIdArr = [];
        foreach ($rows as $row) {
            $torrentIdArr[] = $row['id'];
            $ownerIdArr[] = $row['owner'];
        }
        UserDisplay::preload($ownerIdArr);

        $seedingStatus = $this->torrentStatus->listLeechingSeedingStatus($this->currentUser->id(), $torrentIdArr);
        $tagResult = $this->statsService->getTorrentTagsGrouped($torrentIdArr);

        $showCover = false;
        if ($searchBoxId) {
            $searchBoxExtra = SearchBox::value($cache, $searchBoxId, 'extra');
            if (! empty($searchBoxExtra[\App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST])) {
                $showCover = true;
            }
        }

        $lastBrowse = min((int) $this->currentUser->value('last_browse'), TIMENOW);
        $wait = 0;
        if (UserDisplay::currentClass() < UC_VIP && $waitsystem === 'yes') {
            $ratio = Ratio::forUserId($this->currentUser->id(), false);
            $gigs = $this->currentUser->value('uploaded') / (1024 * 1024 * 1024);
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

        $showComments = (bool) ($this->currentUser->value('showcomnum', false));
        // Columns are only rendered alongside rows; skip the header build
        // (and its lang lookups) for empty listings.
        $columns = $rows === []
            ? []
            : $this->columns($wait > 0, $showComments, (string) ($this->currentUser->value('timetype', '')));

        $caticonrow = Category::iconRowWithContext($this->currentUser->value('caticon'));
        $hasSecondIcon = is_array($caticonrow) && (bool) ($caticonrow['secondicon'] ?? false);

        $posStates = $this->currentUser->value('appendsticky') ? Torrent::listPosStates() : [];
        $appendNew = (bool) ($this->currentUser->value('appendnew', false));
        $showLastCom = $enableTooltip && ($this->currentUser->value('showlastcom', false));
        $timeAlive = ($this->currentUser->value('timetype', null)) == UserTimeType::TIMEALIVE->value;
        $promotionNote = ($this->currentUser->value('appendpromotion', null)) == UserAppendPromotion::HIGHLIGHT->value;
        $canViewAnonymous = Permission::canViewAnonymous();

        $lastcoms = [];
        if ($showComments && $showLastCom) {
            $commentedTorrentIds = [];
            foreach ($rows as $row) {
                if ($row['comments']) {
                    $commentedTorrentIds[] = (int) $row['id'];
                }
            }
            $cachedLastcoms = $commentedTorrentIds === []
                ? []
                : $cache->get_values(array_map(fn ($id) => 'torrent_'.$id.'_last_comment_content', $commentedTorrentIds));
            $uncachedTorrentIds = [];
            foreach ($commentedTorrentIds as $id) {
                $cached = $cachedLastcoms['torrent_'.$id.'_last_comment_content'] ?? false;
                if ($cached) {
                    $lastcoms[$id] = $cached;
                } else {
                    $uncachedTorrentIds[] = $id;
                }
            }
            if ($uncachedTorrentIds !== []) {
                $lastcoms += $this->statsService->getLastComments($uncachedTorrentIds);
                foreach ($uncachedTorrentIds as $id) {
                    $cache->cache_value('torrent_'.$id.'_last_comment_content', $lastcoms[$id] ?? null, 1855);
                }
            }
            UserDisplay::preload(array_map(fn ($l) => (int) ($l['user'] ?? 0), $lastcoms));
        }

        $renderedTt = $lastcoms === []
            ? []
            : $cache->get_values(array_map(
                static fn ($l) => 'fmt_tt_'.md5(self::tooltipText((string) ($l['text'] ?? ''))),
                array_values($lastcoms)
            ));
        $renderTt = function (string $text) use (&$renderedTt, $cache): string {
            $truncated = self::tooltipText($text);
            $key = 'fmt_tt_'.md5($truncated);
            $hit = $renderedTt[$key] ?? false;
            if (is_string($hit)) {
                return $hit;
            }
            $html = (string) Format::formatComment($truncated, true, false, false, true, 600, false, false);
            $cache->cache_value($key, $html, 86400);
            $renderedTt[$key] = $html;

            return $html;
        };

        $outRows = [];
        $lastcomTooltip = [];
        $counter = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $rowClass = Promotion::rowClassWithContext((int) $row['sp_state'], (string) $row['pos_state'], $row);

            $categoryIcon = null;
            $secondIcon = null;
            if (isset($row['category'])) {
                $catData = Category::iconData($row['category']);
                $categoryIcon = new CategoryIcon($catData['iconClass'], $catData['name'], '?cat='.$row['category']);
                if ($hasSecondIcon) {
                    $siData = Category::secondIconData($row);
                    $secondIcon = new CategoryIcon($siData['iconClass'], $siData['name']);
                }
            }

            $nameTitle = trim((string) $row['name']);
            $dispname = $nameTitle;
            if (mb_strlen($dispname, 'UTF-8') > self::MAX_NAME_LENGTH) {
                $dispname = mb_substr($dispname, 0, self::MAX_NAME_LENGTH - 2, 'UTF-8').'..';
            }

            $stickyCount = 0;
            $stickyTitle = '';
            if ($this->currentUser->value('appendsticky')) {
                $posState = $posStates[$row['pos_state']] ?? ['text' => '', 'icon_counts' => 0];
                $stickyCount = (int) ($posState['icon_counts'] ?? 0);
                $stickyTitle = (string) $posState['text'];
            }

            $badges = new TorrentBadgeSet(
                paid: isset($row['price']) && $row['price'] > 0,
                promotion: Promotion::badgeWithContext(
                    $row['sp_state'], '', true, $row['added'],
                    $row['promotion_time_type'], $row['promotion_until'],
                    $row['__ignore_global_sp_state'] ?? false,
                ),
                hitAndRun: TorrentAccess::requiresHrIcon($row, $row['search_box_id']),
                approval: $this->moderationRep->shouldShowApprovalStatusIcon($row['approval_status'])
                    ? new ApprovalBadge(
                        title: (string) Locale::trans("torrent.approval.status_text.{$row['approval_status']}", [], null),
                        icon: Torrent::approvalStatusIcon((int) $row['approval_status']),
                    )
                    : null,
            );

            $tags = [];
            $tagOwns = $tagResult->get($id);
            if ($tagOwns) {
                $ownedTagIds = $tagOwns->pluck('tag_id')->toArray();
                foreach ($this->tagRep->listAll((int) $row['search_box_id']) as $tag) {
                    if (in_array($tag->id, $ownedTagIds)) {
                        $tags[] = $tag;
                    }
                }
            }

            $progress = isset($seedingStatus[$id])
                ? new TorrentProgress(
                    (string) $seedingStatus[$id]['active_status'],
                    min(100.0, max(0.0, (float) $seedingStatus[$id]['progress'] * 100)),
                )
                : null;

            $showDownload = (bool) ($this->currentUser->value('dlicon', false)) && (bool) ($this->currentUser->value('downloadpos', true));
            $showBookmark = (bool) ($this->currentUser->value('bmicon', false));

            $waitText = null;
            $waitClass = null;
            if ($wait) {
                $elapsed = floor((TIMENOW - strtotime((string) $row['added'])) / 3600);
                if ($elapsed < $wait) {
                    $waitClass = Palette::waitRampClass((int) ($wait - $elapsed));
                    $waitText = number_format($wait - $elapsed).__('legacy/functions.text_h');
                } else {
                    $waitText = (string) __('legacy/functions.text_none');
                }
            }

            $commentIsNew = false;
            $tooltipId = null;
            if ($showComments && $row['comments'] && $showLastCom) {
                $lastcom = $lastcoms[$id] ?? null;
                if ($lastcom) {
                    $commentIsNew = $lastcom['user'] != $this->currentUser->id() && strtotime($lastcom['added']) >= $lastBrowse;
                    $lastcomtime = $timeAlive
                        ? __('legacy/functions.text_blank').Time::format($lastcom['added'], true, false, true)
                        : __('legacy/functions.text_at_time').$lastcom['added'];
                    $tooltipId = 'lastcom_'.$counter;
                    $lastcomTooltip[] = [
                        'id' => $tooltipId,
                        'content' => SafeHtml::fromTrustedHtml(
                            trim(view('support._last-comment', [
                                'isNew' => $commentIsNew,
                                'newLabel' => (string) __('legacy/functions.text_new_uppercase'),
                                'byLabel' => (string) __('legacy/functions.text_last_commented_by'),
                                'user' => SafeHtml::fromTrustedHtml(UserDisplay::username($lastcom['user'])->toHtml()),
                                'time' => SafeHtml::fromTrustedHtml((string) $lastcomtime),
                            ])->render())
                            .$renderTt((string) $lastcom['text'])
                        ),
                    ];
                }
            }

            if ($row['seeders']) {
                $seedRatio = $row['leechers'] ? $row['seeders'] / $row['leechers'] : 1;
                $seedersColor = Ratio::seedLeechColorClass($seedRatio) ?: null;
                $seedersUrl = '/web/details/'.$id.'?hit=1&dllist=1#seeders';
                $seedersZeroClass = '';
            } else {
                $seedersColor = null;
                $seedersUrl = null;
                $seedersZeroClass = Palette::seederLink(0);
            }

            $leechersUrl = $row['leechers'] ? '/web/details/'.$id.'?hit=1&dllist=1#leechers' : null;
            $snatchedUrl = $row['times_completed'] >= 1 ? '/web/viewsnatches?id='.$id : null;

            $uploaderAnonymous = $row['anonymous'] == 1;
            $uploaderShowOwner = $uploaderAnonymous
                && ($canViewAnonymous || (isset($row['owner']) && $row['owner'] == $this->currentUser->id()));
            $uploaderName = isset($row['owner'])
                ? UserDisplay::username($row['owner'])
                : null;

            $addedStr = $row['added'] instanceof \DateTimeInterface ? $row['added']->format('Y-m-d H:i:s') : (string) ($row['added'] ?? '');
            [$addedDate, $addedTime] = array_pad(explode(' ', $addedStr, 2), 2, '');

            $outRows[] = new TorrentListRow(
                id: $id,
                rowClass: $rowClass,
                categoryIcon: $categoryIcon,
                secondIcon: $secondIcon,
                coverSrc: $showCover ? (string) ($row['cover'] ?? '') : null,
                stickyCount: $stickyCount,
                stickyTitle: $stickyTitle,
                nameUrl: '/web/details/'.$id.'?hit=1',
                displayName: $dispname,
                nameTitle: $nameTitle,
                isNew: $appendNew && strtotime((string) $row['added']) >= $lastBrowse,
                isBanned: $row['banned'] == 1,
                badges: $badges,
                tags: $tags,
                progress: $progress,
                showDownload: $showDownload,
                downloadUrl: '/download?id='.$id,
                showBookmark: $showBookmark,
                waitText: $waitText,
                waitClass: $waitClass,
                commentsUrl: '/web/details/'.$id.'?hit=1&cmtpage=1#startcomments',
                comments: (int) $row['comments'],
                commentIsNew: $commentIsNew,
                lastCommentTooltipId: $tooltipId,
                added: is_int($row['added']) || is_string($row['added']) || $row['added'] instanceof \DateTimeInterface ? $row['added'] : null,
                addedDate: $addedDate,
                addedTime: $addedTime,
                size: Format::sizeParts((float) $row['size']),
                seedersUrl: $seedersUrl,
                seeders: (int) $row['seeders'],
                seedersClass: $seedersColor,
                seedersZeroClass: $seedersZeroClass,
                leechersUrl: $leechersUrl,
                leechers: (int) $row['leechers'],
                snatchedUrl: $snatchedUrl,
                snatched: (int) $row['times_completed'],
                uploaderAnonymous: $uploaderAnonymous,
                uploaderShowOwner: $uploaderShowOwner,
                uploaderName: $uploaderName,
            );
            $counter++;
        }

        // The legacy renderer also emitted a second (always empty) tooltip
        // container — dropped here; $torrent_tooltip was never populated.
        return new TorrentListViewModel(
            columns: $columns,
            rows: $outRows,
            showComments: $showComments,
            showPromotionNote: $promotionNote,
            lastCommentTooltips: ($enableTooltip && (empty($user) || ($this->currentUser->value('showlastcom', false))))
                ? $lastcomTooltip
                : [],
        );
    }

    /**
     * Column descriptors for the table head. Sort links preserve the
     * current query minus sort/type, same as the legacy renderer.
     *
     * @return list<array{key: string, label: string, iconClass: string, iconTitle: string, sortUrl: ?string}>
     */
    private function columns(bool $showWait, bool $showComments, string $timetype): array
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
        $sortedClass = static function (int $i) use ($sort): string {
            return ((string) $sort === (string) $i) ? ' nxm-th--sorted' : '';
        };

        $columns = [
            ['key' => 'type', 'label' => (string) __('legacy/functions.col_type'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => null],
            ['key' => 'name', 'label' => (string) __('legacy/functions.col_name'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => $sortUrl(1), 'thClass' => ltrim($sortedClass(1))],
        ];
        if ($showWait) {
            $columns[] = ['key' => 'wait', 'label' => (string) __('legacy/functions.col_wait'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => null];
        }
        if ($showComments) {
            $columns[] = ['key' => 'comments', 'label' => '', 'shortLabel' => 'Com', 'iconClass' => 'comments', 'iconTitle' => (string) __('legacy/functions.title_number_of_comments'), 'sortUrl' => $sortUrl(3), 'thClass' => ltrim($sortedClass(3))];
        }
        $columns[] = ['key' => 'time', 'label' => '', 'shortLabel' => 'Added', 'iconClass' => 'time', 'iconTitle' => $timetype != UserTimeType::TIMEALIVE->value ? (string) __('legacy/functions.title_time_added') : (string) __('legacy/functions.title_time_alive'), 'sortUrl' => $sortUrl(4), 'thClass' => ltrim($sortedClass(4))];
        $columns[] = ['key' => 'size', 'label' => '', 'shortLabel' => (string) __('legacy/functions.text_size'), 'iconClass' => 'size', 'iconTitle' => (string) __('legacy/functions.title_size'), 'sortUrl' => $sortUrl(5), 'thClass' => ltrim($sortedClass(5))];
        $columns[] = ['key' => 'seeders', 'label' => '', 'shortLabel' => '', 'thClass' => 'nx-center'.$sortedClass(7), 'iconClass' => 'seeders', 'iconTitle' => (string) __('legacy/functions.title_number_of_seeders'), 'sortUrl' => $sortUrl(7)];
        $columns[] = ['key' => 'leechers', 'label' => '', 'shortLabel' => '', 'thClass' => 'nx-center'.$sortedClass(8), 'iconClass' => 'leechers', 'iconTitle' => (string) __('legacy/functions.title_number_of_leechers'), 'sortUrl' => $sortUrl(8)];
        $columns[] = ['key' => 'snatched', 'label' => '', 'shortLabel' => '', 'thClass' => 'nx-center'.$sortedClass(6), 'iconClass' => 'snatched', 'iconTitle' => (string) __('legacy/functions.title_number_of_snatched'), 'sortUrl' => $sortUrl(6)];
        $columns[] = ['key' => 'uploader', 'label' => (string) __('legacy/functions.col_uploader'), 'iconClass' => '', 'iconTitle' => '', 'sortUrl' => $sortUrl(9), 'thClass' => ltrim($sortedClass(9))];

        return $columns;
    }

    private static function tooltipText(string $body): string
    {
        return mb_substr($body, 0, 100, 'UTF-8').(mb_strlen($body, 'UTF-8') > 100 ? ' ......' : '');
    }
}
