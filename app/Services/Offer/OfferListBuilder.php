<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserTimeType;
use App\Support\Cache\NexusCache;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\YesNo;
use App\ViewModels\Offer\OfferAllowedBadge;
use App\ViewModels\Offer\OfferCategoryOption;
use App\ViewModels\Offer\OfferCommentCell;
use App\ViewModels\Offer\OfferListViewModel;
use App\ViewModels\Offer\OfferRow;
use App\ViewModels\Offer\OfferRulesViewModel;
use App\ViewModels\Offer\OfferTableViewModel;
use App\ViewModels\Offer\OfferTooltip;
use App\ViewModels\Offer\OfferVoteResults;
use App\ViewModels\Torrent\CategoryIcon;
use Illuminate\Http\Request;

/**
 * Builds the offer listing (sort, pager, rules, table rows + tooltips).
 */
final class OfferListBuilder
{
    public function __construct(
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferCommentRepositoryInterface $offerCommentRepository,
        private readonly NexusCache $cache,
        private readonly UsercpRepositoryInterface $usercpRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $globalData
     */
    public function build(array $curUser, int $userId, Request $request, array $globalData): OfferListViewModel
    {
        // Validate sort
        $sort = '';
        $sortParam = (string) $request->query('sort', '');
        $allowedSorts = ['cat', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];
        if (in_array($sortParam, $allowedSorts, true)) {
            $sort = $sortParam;
        } elseif ($sortParam !== '') {
            LegacyResponse::abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $catOrderType = 'desc';
        $nameOrderType = 'desc';
        $addedOrderType = 'desc';
        $commentsOrderType = 'desc';
        $vResOrderType = 'desc';

        $sortColumn = '';
        if ($sort === 'cat') {
            if ($request->query('type') === 'desc') {
                $catOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY category '.$catOrderType;
        } elseif ($sort === 'name') {
            if ($request->query('type') === 'desc') {
                $nameOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY name '.$nameOrderType;
        } elseif ($sort === 'added') {
            if ($request->query('type') === 'desc') {
                $addedOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY added '.$addedOrderType;
        } elseif ($sort === 'comments') {
            if ($request->query('type') === 'desc') {
                $commentsOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY comments '.$commentsOrderType;
        } elseif ($sort === 'v_res') {
            if ($request->query('type') === 'desc') {
                $vResOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY (yeah - against) '.$vResOrderType;
        }

        $direction = strtolower((string) $request->query('type', '')) === 'asc' ? 'asc' : 'desc';
        $perpage = 25;
        $categ = (int) $request->query('category', 0);

        $offerorid = 0;
        if ($request->query('offerorid') !== null && $request->query('offerorid') !== '') {
            $offerorid = (int) $request->query('offerorid', 0);
        }

        $search = (string) ($request->query('search', '') ?? '');

        $self = Input::serverValue('PHP_SELF');
        $offerResult = $this->offerRepository->getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, 0, 0);
        $count = (int) $offerResult['count'];

        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager(
            $perpage,
            $count,
            $self.'?'.'category='.((is_string($cat = $request->query('category', '')) ? $cat : '')).'&sort='.((is_string($sortQ = $request->query('sort', '')) ? $sortQ : '')).'&'
        );

        $offerResult = $this->offerRepository->getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, (int) $offset, (int) $perpage);
        $offerRows = $offerResult['rows'];
        $num = $offerRows->count();

        // Rules section
        $rules = new OfferRulesViewModel(
            uploadClassName: UserClass::name((int) $globalData['uploadClass'], false, true, true),
            addofferClassName: UserClass::name((int) $globalData['addofferClass'], false, true, true),
            skipApprovedCount: ($c = SiteConfig::current()->main->offerSkipApprovedCount()) > 0
                ? $c
                : null,
            minVotes: (int) $globalData['minoffervotes'],
            showVoteTimeout: $globalData['offervotetimeoutMain'] > 0,
            voteTimeoutHours: (int) ($globalData['offervotetimeoutMain'] / 3600),
            showUpTimeout: $globalData['offeruptimeoutMain'] > 0,
            upTimeoutHours: (int) ($globalData['offeruptimeoutMain'] / 3600),
        );

        $categories = [];
        foreach (Category::listByModeWithContext($globalData['browsecatmode']) as $cat) {
            $catArr = (array) $cat;
            $categories[] = new OfferCategoryOption((int) $catArr['id'], (string) $catArr['name']);
        }

        // Build the table rows
        $last_offer = strtotime((string) ($curUser['last_offer'] ?? 'now'));
        $table = null;
        $emptyState = SafeHtml::fromTrustedHtml('');
        if (! $num) {
            $emptyState = SafeHtml::fromTrustedHtml(view('partials.std-message', [
                'heading' => __('offers.text_nothing_found'),
                'text' => __('offers.text_nothing_found'),
                'htmlstrip' => false,
                'body' => null,
            ])->render());
        } else {
            $catid = is_string($catq = $request->query('category', '')) ? $catq : '';
            $sortUrl = static fn (string $column, string $type): string => '?category='.$catid.'&sort='.$column.'&type='.$type;

            $showTimeout = $globalData['offervotetimeoutMain'] > 0 && $globalData['offeruptimeoutMain'] > 0;
            $canManage = Permission::can(PermissionEnum::OFFER_MANAGE);
            $showAgainstCell = UserDisplay::currentClass() >= $globalData['againstofferClass'];
            $canAgainst = Permission::can(PermissionEnum::AGAINST_OFFER);
            $showlastcom = (bool) ($curUser['showlastcom'] ?? true);

            $lastcoms = [];
            $lastcomUserIds = [];
            $commentedOfferIds = [];
            foreach ($offerRows as $row) {
                $arr = (array) $row;
                if ((int) ($arr['comments'] ?? 0) !== 0) {
                    $commentedOfferIds[] = (int) $arr['id'];
                }
            }
            $cachedLastcoms = $commentedOfferIds === []
                ? []
                : $this->cache->getMany(array_map(fn ($id) => 'offer_'.$id.'_last_comment_content', $commentedOfferIds));
            $uncachedOfferIds = [];
            foreach ($commentedOfferIds as $offerId) {
                $lastcom = $cachedLastcoms['offer_'.$offerId.'_last_comment_content'] ?? false;
                if ($lastcom) {
                    $lastcoms[$offerId] = (array) $lastcom;
                } else {
                    $uncachedOfferIds[] = $offerId;
                }
            }
            if ($uncachedOfferIds !== []) {
                foreach ($this->offerCommentRepository->getLastComments($uncachedOfferIds) as $offerId => $lastcom) {
                    $lastcoms[$offerId] = $lastcom;
                    $this->cache->put('offer_'.$offerId.'_last_comment_content', $lastcom, 1855);
                }
            }
            foreach ($lastcoms as $lastcom) {
                $lastcomUserIds[] = (int) ($lastcom['user'] ?? 0);
            }
            UserDisplay::preload($lastcomUserIds);

            $renderedTt = $lastcoms === []
                ? []
                : $this->cache->getMany(array_map(
                    static fn ($l) => 'fmt_tt_'.md5(self::tooltipText((string) ($l['text'] ?? ''))),
                    array_values($lastcoms)
                ));
            $renderTt = function (string $text) use (&$renderedTt): string {
                $truncated = self::tooltipText($text);
                $key = 'fmt_tt_'.md5($truncated);
                $hit = $renderedTt[$key] ?? false;
                if (is_string($hit)) {
                    return $hit;
                }
                $html = (string) Format::formatComment($truncated, true, false, false, true, 600, false, false);
                $this->cache->put($key, $html, 86400);
                $renderedTt[$key] = $html;

                return $html;
            };

            $i = 0;
            $rows = [];
            $tooltips = [];
            foreach ($offerRows as $row) {
                $arr = (array) $row;
                $offerId = (int) $arr['id'];
                $comms = (int) ($arr['comments'] ?? 0);
                if ($comms === 0) {
                    $comment = new OfferCommentCell(
                        count: 0,
                        href: '/comment?action=add&pid='.$offerId.'&type=offer',
                        hasNew: false,
                        title: __('offers.title_add_comments'),
                        tooltipId: null,
                    );
                } else {
                    $lastcom = $lastcoms[$offerId] ?? [];
                    $timestamp = strtotime((string) ($lastcom['added'] ?? 'now'));
                    $hasnewcom = (($lastcom['user'] ?? 0) !== $userId && $timestamp >= $last_offer);
                    $title = null;
                    $tooltipId = null;
                    if ($showlastcom) {
                        if (! empty($lastcom)) {
                            if (($curUser['timetype'] ?? 1) !== UserTimeType::TIMEALIVE->value) {
                                $lastcomtime = __('offers.text_at_time').($lastcom['added'] ?? '');
                            } else {
                                $lastcomtime = __('offers.text_blank').Time::format((string) ($lastcom['added'] ?? 'now'), true, false, true);
                            }
                            $tooltipId = 'lastcom_'.$i;
                            $tooltips[] = new OfferTooltip(
                                id: $tooltipId,
                                content: SafeHtml::fromTrustedHtml(
                                    view('offers._lastcom_tooltip', [
                                        'hasNew' => $hasnewcom,
                                        'username' => UserDisplay::username((int) ($lastcom['user'] ?? 0)),
                                        'time' => SafeHtml::fromTrustedHtml($lastcomtime),
                                        'comment' => $renderTt((string) ($lastcom['text'] ?? '')),
                                    ])->render()
                                ),
                            );
                        }
                    } else {
                        $title = (string) ($hasnewcom ? (__('offers.title_has_new_comment')) : (__('offers.title_no_new_comment')));
                    }
                    $comment = new OfferCommentCell(
                        count: $comms,
                        href: '?id='.$offerId.'&off_details=1#startcomments',
                        hasNew: $hasnewcom,
                        title: $title,
                        tooltipId: $tooltipId,
                    );
                }

                $allowed = match ((int) ($arr['allowed'] ?? 1)) {
                    OfferAllowed::ALLOWED->value => new OfferAllowedBadge(__('offers.text_allowed'), 'nx-color-green'),
                    OfferAllowed::DENIED->value => new OfferAllowedBadge(__('offers.text_denied'), 'nx-color-red'),
                    default => new OfferAllowedBadge(__('offers.text_pending'), 'nx-color-orange'),
                };

                $yeah = (int) ($arr['yeah'] ?? 0);
                $against = (int) ($arr['against'] ?? 0);
                $voteResults = ($yeah === 0 && $against === 0)
                    ? null
                    : new OfferVoteResults($yeah, $against, '?id='.$offerId.'&offer_vote=1');

                $addtime = SafeHtml::fromTrustedHtml((string) Time::format((string) ($arr['added'] ?? 'now'), false, true));
                $dispname = (string) ($arr['name'] ?? '');
                if (mb_strlen($dispname, 'UTF-8') > 70) {
                    $dispname = mb_substr($dispname, 0, 68, 'UTF-8').'..';
                }

                $timeout = SafeHtml::fromTrustedHtml('N/A');
                if ($showTimeout) {
                    $timeoutStr = '';
                    if ((int) ($arr['allowed'] ?? 1) === OfferAllowed::ALLOWED->value) {
                        $futuretime = strtotime((string) ($arr['allowedtime'] ?? 'now')) + $globalData['offeruptimeoutMain'];
                        $timeoutStr = (string) Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
                    } elseif ((int) ($arr['allowed'] ?? 1) === OfferAllowed::PENDING->value) {
                        $futuretime = strtotime((string) ($arr['added'] ?? 'now')) + $globalData['offervotetimeoutMain'];
                        $timeoutStr = (string) Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
                    }
                    $timeout = SafeHtml::fromTrustedHtml($timeoutStr !== '' ? $timeoutStr : 'N/A');
                }

                $catIconData = Category::iconData((int) ($arr['cat_id'] ?? 0));
                $rows[] = new OfferRow(
                    id: $offerId,
                    categoryIcon: new CategoryIcon($catIconData['iconClass'], $catIconData['name'], '?category='.(int) ($arr['cat_id'] ?? 0)),
                    displayName: $dispname,
                    fullName: (string) ($arr['name'] ?? ''),
                    isNew: ! YesNo::isNo($curUser['appendnew'] ?? null) && strtotime((string) ($arr['added'] ?? 'now')) >= $last_offer,
                    allowed: $allowed,
                    voteResults: $voteResults,
                    comment: $comment,
                    addedTime: $addtime,
                    timeout: $timeout,
                    offeredBy: UserDisplay::username((int) ($arr['userid'] ?? 0)),
                );
                $i++;
            }

            $table = new OfferTableViewModel(
                sortCatUrl: $sortUrl('cat', $catOrderType),
                sortNameUrl: $sortUrl('name', $nameOrderType),
                sortVResUrl: $sortUrl('v_res', $vResOrderType),
                sortCommentsUrl: $sortUrl('comments', $commentsOrderType),
                sortAddedUrl: $sortUrl('added', $addedOrderType),
                showTimeout: $showTimeout,
                canManage: $canManage,
                canAgainst: $canAgainst,
                showAgainstCell: $showAgainstCell,
                rows: $rows,
                tooltips: $showlastcom ? $tooltips : [],
                pagerBottom: SafeHtml::fromTrustedHtml($pagerBottom),
            );
        }

        // Update last_offer timestamp
        if ($curUser) {
            $this->usercpRepository->updateLastOffer($userId);
        }

        return new OfferListViewModel(
            rules: $rules,
            canAddOffer: Permission::can(PermissionEnum::ADD_OFFER),
            categories: $categories,
            table: $table,
            emptyState: $emptyState,
            count: $count,
        );
    }

    private static function tooltipText(string $body): string
    {
        return mb_substr($body, 0, 100, 'UTF-8').(mb_strlen($body, 'UTF-8') > 100 ? ' ......' : '');
    }
}
