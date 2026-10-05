<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\OfferVote;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserTimeType;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\LegacyYesNo;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\ViewModels\Offer\OfferAllowedBadge;
use App\ViewModels\Offer\OfferCategoryOption;
use App\ViewModels\Offer\OfferCommentCell;
use App\ViewModels\Offer\OfferDetailsViewModel;
use App\ViewModels\Offer\OfferListViewModel;
use App\ViewModels\Offer\OfferRow;
use App\ViewModels\Offer\OfferRulesViewModel;
use App\ViewModels\Offer\OfferTableViewModel;
use App\ViewModels\Offer\OfferTooltip;
use App\ViewModels\Offer\OfferVoteResults;
use App\ViewModels\OfferPageViewModel;
use App\ViewModels\Torrent\CategoryIcon;
use Illuminate\Http\Request;

final class OfferPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
        private readonly OfferCommentRepositoryInterface $offerCommentRepository,
        private readonly LegacyRedisCache $cache,
        private readonly UsercpRepositoryInterface $usercpRepository,
    ) {}

    public function build(Request $request): OfferPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $action = $this->resolveAction($request);

        $data = [
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost'),
            'contentWidth' => (string) $this->globals->get('CONTENT_WIDTH', '737'),
            'browsecatmode' => SiteConfig::current()->main->browseCat(1),
            'enableoffer' => SiteConfig::current()->main->showOffer(true) ? 'yes' : 'no',
            'minoffervotes' => SiteConfig::current()->main->minOfferVotes(),
            'offervotetimeoutMain' => SiteConfig::current()->main->offerVoteTimeout(0),
            'offeruptimeoutMain' => SiteConfig::current()->main->offerUploadTimeout(0),
            'offervoteBonus' => SiteConfig::current()->bonus->offerVote(),
            'uploadClass' => (int) SiteConfig::current()->authority->permission('upload', 0),
            'addofferClass' => (int) SiteConfig::current()->authority->permission('addoffer', 0),
            'againstofferClass' => (int) SiteConfig::current()->authority->permission('againstoffer', 0),
        ];

        if ($data['enableoffer'] === 'no') {
            LegacyResponse::permissionDenied();
        }

        switch ($action) {
            case 'add_offer':
                Permission::assertCan(PermissionEnum::ADD_OFFER);
                $data['add_offer'] = $this->buildAddOffer($data['browsecatmode']);
                break;
            case 'off_details':
                $data['off_details'] = $this->buildOfferDetails($curUser, $userId, $request);
                break;
            case 'edit_offer':
                $data['edit_offer'] = $this->buildEditOffer($curUser, $userId, $request, $data['browsecatmode']);
                break;
            case 'offer_vote':
                $data['offer_vote'] = $this->buildOfferVoteList($request);
                break;
            default:
                $data['list'] = $this->buildOfferList($curUser, $userId, $request, $data);
                $data['action'] = 'list';
                break;
        }

        return new OfferPageViewModel(
            curUser: $data['curUser'],
            userId: $data['userId'],
            action: $data['action'],
            baseUrl: $data['baseUrl'],
            contentWidth: $data['contentWidth'],
            browsecatmode: $data['browsecatmode'],
            enableoffer: $data['enableoffer'],
            minoffervotes: $data['minoffervotes'],
            offervotetimeoutMain: $data['offervotetimeoutMain'],
            offeruptimeoutMain: $data['offeruptimeoutMain'],
            offervoteBonus: $data['offervoteBonus'],
            uploadClass: $data['uploadClass'],
            addofferClass: $data['addofferClass'],
            againstofferClass: $data['againstofferClass'],
            add_offer: $data['add_offer'] ?? null,
            off_details: $data['off_details'] ?? null,
            edit_offer: $data['edit_offer'] ?? null,
            offer_vote: $data['offer_vote'] ?? null,
            list: $data['list'] ?? null,
        );
    }

    private function resolveAction(Request $request): string
    {
        foreach (['add_offer', 'off_details', 'edit_offer', 'offer_vote'] as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '' && $value !== '0') {
                return $key;
            }
        }

        return 'list';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddOffer(mixed $browsecatmode): array
    {
        $typeOptions = [];
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $typeOptions[] = new OfferCategoryOption((int) $rowArr['id'], (string) $rowArr['name']);
        }

        return [
            'typeOptions' => $typeOptions,
            'bodyContent' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     */
    private function buildOfferDetails(array $curUser, int $userId, Request $request): OfferDetailsViewModel
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            LegacyResponse::abort((string) (__('legacy/offers.std_error')), (string) (__('legacy/offers.std_smell_rat')));
        }

        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            LegacyResponse::abort((string) (__('legacy/offers.std_error')), (string) (__('legacy/offers.text_nothing_found')));
        }
        $num = $offer->toArray();

        $timeFormat = Time::format((string) ($num['added'] ?? ''), true, false);
        $offertime = ($curUser['timetype'] ?? 1) !== UserTimeType::TIMEALIVE->value
            ? (string) (__('legacy/offers.text_at')).$timeFormat
            : (string) (__('legacy/offers.text_blank')).$timeFormat;

        $status = match ((int) ($num['allowed'] ?? 1)) {
            OfferAllowed::PENDING->value => new OfferAllowedBadge((string) (__('legacy/offers.text_pending')), 'nx-color-red'),
            OfferAllowed::ALLOWED->value => new OfferAllowedBadge((string) (__('legacy/offers.text_allowed')), 'nx-color-green'),
            default => new OfferAllowedBadge((string) (__('legacy/offers.text_denied')), 'nx-color-red'),
        };

        $voteCounts = $this->offerVoteRepository->getVoteCounts($id);
        $yeah = (int) $voteCounts['yeah'];
        $against = (int) $voteCounts['against'];

        $isPending = (int) ($num['allowed'] ?? 1) === OfferAllowed::PENDING->value;
        $allowed = (int) ($num['allowed'] ?? 1) === OfferAllowed::ALLOWED->value;

        $allowedNote = '';
        if ($allowed && $userId !== (int) ($num['userid'] ?? 0)) {
            $allowedNote = (string) (__('legacy/offers.text_voter_receives_pm_note'));
        }
        if ($allowed && $userId === (int) ($num['userid'] ?? 0)) {
            $allowedNote = (string) (__('legacy/offers.text_urge_upload_offer_note'));
        }

        $description = '';
        if (! empty($num['descr'])) {
            $descrKey = 'fmt_offer_'.md5((string) $num['descr']);
            $cachedDescr = $this->cache->get_value($descrKey);
            if (is_string($cachedDescr)) {
                $description = SafeHtml::fromTrustedHtml($cachedDescr);
            } else {
                $description = Format::formatComment((string) $num['descr']);
                $this->cache->cache_value($descrKey, (string) $description, 86400);
            }
        }

        // Comments section
        $commentCount = $this->offerCommentRepository->countComments($id);

        $pagerTop = '';
        $pagerBottom = '';
        if ($commentCount) {
            [$pagerTop, $pagerBottom] = Pagination::pager(10, $commentCount, "offers.php?id={$id}&off_details=1&", ['lastpagedefault' => 1]);
        }

        return new OfferDetailsViewModel(
            id: $id,
            name: (string) ($num['name'] ?? ''),
            offeredBy: UserDisplay::username((int) ($num['userid'] ?? 0)),
            offerTime: SafeHtml::fromTrustedHtml($offertime),
            status: $status,
            showAllowRow: Permission::can(PermissionEnum::OFFER_MANAGE) && $isPending,
            isPending: $isPending,
            canAgainst: Permission::can(PermissionEnum::AGAINST_OFFER),
            yeah: $yeah,
            against: $against,
            allowedNote: $allowedNote,
            showEditDelete: $userId === (int) ($num['userid'] ?? 0) || Permission::can(PermissionEnum::OFFER_MANAGE),
            description: SafeHtml::fromTrustedHtml($description),
            commentCount: $commentCount,
            pagerTop: SafeHtml::fromTrustedHtml($pagerTop),
            pagerBottom: SafeHtml::fromTrustedHtml($pagerBottom),
        );
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildEditOffer(array $curUser, int $userId, Request $request, mixed $browsecatmode): array
    {
        $id = (int) $request->query('id', 0);
        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            LegacyResponse::abort((string) (__('legacy/offers.std_error')), (string) (__('legacy/offers.text_nothing_found')));
        }
        $num = $offer->toArray();

        if ($userId !== (int) ($num['userid'] ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            LegacyResponse::abort((string) (__('legacy/offers.std_error')), (string) (__('legacy/offers.std_cannot_edit_others_offer')));
        }

        $body = htmlspecialchars(Input::unescape((string) ($num['descr'] ?? '')));
        $id2 = (int) ($num['category'] ?? 0);

        $catOptions = [];
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $catOptions[] = new OfferCategoryOption((int) $rowArr['id'], (string) $rowArr['name']);
        }

        return [
            'id' => $id,
            'title' => htmlspecialchars(trim((string) ($num['name'] ?? ''))),
            'catId' => $id2,
            'catOptions' => $catOptions,
            'bodyContent' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $globalData
     */
    private function buildOfferList(array $curUser, int $userId, Request $request, array $globalData): OfferListViewModel
    {
        // Validate sort
        $sort = '';
        $sortParam = (string) $request->query('sort', '');
        $allowedSorts = ['cat', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];
        if (in_array($sortParam, $allowedSorts, true)) {
            $sort = $sortParam;
        } elseif ($sortParam !== '') {
            LegacyResponse::abort((string) (__('legacy/offers.std_error')), (string) (__('legacy/offers.std_smell_rat')));
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
                'heading' => (string) (__('legacy/offers.text_nothing_found')),
                'text' => (string) (__('legacy/offers.text_nothing_found')),
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
                : $this->cache->get_values(array_map(fn ($id) => 'offer_'.$id.'_last_comment_content', $commentedOfferIds));
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
                    $this->cache->cache_value('offer_'.$offerId.'_last_comment_content', $lastcom, 1855);
                }
            }
            foreach ($lastcoms as $lastcom) {
                $lastcomUserIds[] = (int) ($lastcom['user'] ?? 0);
            }
            UserDisplay::preload($lastcomUserIds);

            $renderedTt = $lastcoms === []
                ? []
                : $this->cache->get_values(array_map(
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
                $this->cache->cache_value($key, $html, 86400);
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
                        href: 'comment.php?action=add&pid='.$offerId.'&type=offer',
                        hasNew: false,
                        title: (string) (__('legacy/offers.title_add_comments')),
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
                                $lastcomtime = (string) (__('legacy/offers.text_at_time')).($lastcom['added'] ?? '');
                            } else {
                                $lastcomtime = (string) (__('legacy/offers.text_blank')).Time::format((string) ($lastcom['added'] ?? 'now'), true, false, true);
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
                        $title = (string) ($hasnewcom ? (__('legacy/offers.title_has_new_comment')) : (__('legacy/offers.title_no_new_comment')));
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
                    OfferAllowed::ALLOWED->value => new OfferAllowedBadge((string) (__('legacy/offers.text_allowed')), 'nx-color-green'),
                    OfferAllowed::DENIED->value => new OfferAllowedBadge((string) (__('legacy/offers.text_denied')), 'nx-color-red'),
                    default => new OfferAllowedBadge((string) (__('legacy/offers.text_pending')), 'nx-color-orange'),
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
                    isNew: ! LegacyYesNo::isNo($curUser['appendnew'] ?? null) && strtotime((string) ($arr['added'] ?? 'now')) >= $last_offer,
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

    /**
     * @return array<string, mixed>
     */
    private function buildOfferVoteList(Request $request): array
    {
        $offerId = (int) $request->query('id', 0);
        $count = $this->offerVoteRepository->getVoteCount($offerId);
        $offerName = (string) $this->offerRepository->getOfferName($offerId);

        $perpage = 25;
        $self = Input::serverValue('PHP_SELF');
        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $self.'?id='.$offerId.'&offer_vote=1&');
        $voteRows = $this->offerVoteRepository->getVoteRows($offerId, (int) $offset, (int) $perpage);

        UserDisplay::preload($voteRows->map(fn ($r) => (int) (((array) $r)['userid'] ?? 0))->all());
        $rows = [];
        foreach ($voteRows as $arr) {
            $arrArr = (array) $arr;
            $rows[] = [
                'username' => UserDisplay::username((int) ($arrArr['userid'] ?? 0)),
                'vote' => OfferVote::tryFrom((int) ($arrArr['vote'] ?? -1))?->stringValue() ?? 'unknown',
            ];
        }

        return [
            'offerId' => $offerId,
            'offerName' => htmlspecialchars($offerName),
            'hasVotes' => ! $voteRows->isEmpty(),
            'noVotesNote' => (string) (__('legacy/offers.std_no_votes_yet')),
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'rows' => $rows,
        ];
    }

    private static function tooltipText(string $body): string
    {
        return mb_substr($body, 0, 100, 'UTF-8').(mb_strlen($body, 'UTF-8') > 100 ? ' ......' : '');
    }
}
