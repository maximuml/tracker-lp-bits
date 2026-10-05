<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\ReportType;
use App\Repositories\CommentRepository;
use App\Repositories\ModerationRepository;
use App\Services\PermissionChecker;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ModerationController extends LegacyController
{
    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly OfferRepositoryInterface $offerRepository, private readonly CommentRepository $commentRepository, private readonly TorrentRepositoryInterface $torrentRepository, private readonly UserRepositoryInterface $userRepository,
        private readonly CurrentUser $currentUser,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly ModerationRepository $moderationRepository,
    ) {}

    public function reportAction(Request $request): View|RedirectResponse|Response
    {
        return $this->report($request);
    }

    public function report(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $staffmemClass = defined('UC_STAFFMEM') ? \constant('UC_STAFFMEM') : (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);

        $cache = $this->legacyRedisCache;

        $reportofferid = (int) (request()->query('reportofferid') ?? 0);
        $user = (int) (request()->query('user') ?? 0);
        $commentid = (int) (request()->query('commentid') ?? 0);
        $torrent = (int) (request()->query('torrent') ?? 0);
        $forumpost = (int) (request()->query('forumpost') ?? 0);

        $takeuser = (int) (request()->post('takeuser') ?? 0);
        $takecommentid = (int) (request()->post('takecommentid') ?? 0);
        $taketorrent = (int) (request()->post('taketorrent') ?? 0);
        $takeforumpost = (int) (request()->post('takeforumpost') ?? 0);
        $takereportofferid = (int) (request()->post('takereportofferid') ?? 0);
        $takereason = trim((string) request()->post('reason'));

        $repo = $this->moderationRepository;
        $doTakeReport = function (int $reportid, string $type, string $reason) use ($currentUserId, $cache, $repo): Response {
            if (! Validators::isId($reportid) || $reason === '') {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_missing_reason'));
            }

            if ($repo->reportExists($currentUserId, $reportid, $type)) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_already_reported_this'));
            }

            $repo->createReport([
                'addedby' => $currentUserId,
                'reportid' => $reportid,
                'type' => $type,
                'reason' => $reason,
                'added' => date('Y-m-d H:i:s'),
            ]);

            $cache?->delete_value('staff_report_count');
            $cache?->delete_value('staff_new_report_count');

            return $this->legacyAbortResponse(__('legacy/report.std_message'), __('legacy/report.std_successfully_reported'), false);
        };

        if ($takereportofferid && Validators::isId($takereportofferid)) {
            return $doTakeReport($takereportofferid, 'offer', $takereason);
        }
        if ($takeuser && Validators::isId($takeuser)) {
            return $doTakeReport($takeuser, 'user', $takereason);
        }
        if ($taketorrent && Validators::isId($taketorrent)) {
            return $doTakeReport($taketorrent, 'torrent', $takereason);
        }
        if ($takeforumpost && Validators::isId($takeforumpost)) {
            return $doTakeReport($takeforumpost, 'post', $takereason);
        }
        if ($takecommentid && Validators::isId($takecommentid)) {
            return $doTakeReport($takecommentid, 'comment', $takereason);
        }

        if ($user && Validators::isId($user)) {
            if ($user == $currentUserId) {
                return $this->legacyAbortResponse(__('legacy/report.std_sorry'), __('legacy/report.std_cannot_report_oneself'));
            }
            $userRow = $this->userRepository->findById((int) $user, ['username', 'class']);
            if (! $userRow) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_user_id'));
            }
            $arr = $userRow->toArray();
            if ((int) ($arr['class'] ?? 0) >= $staffmemClass) {
                $msg = (__('legacy/report.std_cannot_report')).UserClass::name((int) ($arr['class'] ?? 0), false, true, true);

                return $this->legacyAbortResponse(__('legacy/report.std_sorry'), $msg);
            }

            $form = view('moderation._confirm', [
                'pre' => (string) __('legacy/report.text_are_you_sure_user'),
                'kind' => 'user',
                'userHtml' => UserDisplay::username($user),
                'mid' => (string) __('legacy/report.text_to_staff'),
                'extraNote' => (string) __('legacy/report.text_not_for_leechers'),
                'field' => 'takeuser',
                'id' => $user,
            ])->render();

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($torrent && Validators::isId($torrent)) {
            $name = $this->torrentRepository->getNameById((int) $torrent);
            if (! $name) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_torrent_id'));
            }
            $form = view('moderation._confirm', [
                'pre' => (string) __('legacy/report.text_are_you_sure_torrent'),
                'kind' => 'torrent',
                'id' => $torrent,
                'name' => (string) $name,
                'mid' => (string) __('legacy/report.text_to_staff'),
                'extraNote' => null,
                'field' => 'taketorrent',
            ])->render();

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($forumpost && Validators::isId($forumpost)) {
            $arr = $this->moderationRepository->getForumPost($forumpost);
            if ($arr === null) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_post_id'));
            }
            $form = view('moderation._confirm', [
                'pre' => (string) __('legacy/report.text_are_you_sure_post'),
                'kind' => 'post',
                'id' => $forumpost,
                'topicid' => $arr['topicid'],
                'subject' => (string) $arr['subject'],
                'userHtml' => UserDisplay::username($arr['postuserid']),
                'mid' => (string) __('legacy/report.text_to_staff'),
                'extraNote' => null,
                'field' => 'takeforumpost',
            ])->render();

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($commentid && Validators::isId($commentid)) {
            $comment = $this->commentRepository->findById((int) $commentid, ['id', 'user', 'torrent', 'offer']);
            if (! $comment) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_comment_id'));
            }
            $arr = $comment->toArray();
            if ($arr['torrent'] ?? null) {
                $name = $this->torrentRepository->getNameById((int) $arr['torrent']);
                $url = '/web/details/'.$arr['torrent'].'#'.$commentid;
                $of = __('legacy/report.text_of_torrent');
            } elseif ($arr['offer'] ?? null) {
                $name = $this->offerRepository->getOfferName((int) $arr['offer']);
                $url = '/web/offers?id='.$arr['offer'].'&off_details=1#'.$commentid;
                $of = __('legacy/report.text_of_offer');
            } else {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_orphaned_comment'));
            }
            $form = view('moderation._confirm', [
                'pre' => (string) __('legacy/report.text_are_you_sure_comment'),
                'kind' => 'comment',
                'id' => $commentid,
                'of' => (string) $of,
                'url' => $url,
                'name' => (string) $name,
                'userHtml' => UserDisplay::username((int) ($arr['user'] ?? 0)),
                'mid' => (string) __('legacy/report.text_to_staff'),
                'extraNote' => null,
                'field' => 'takecommentid',
            ])->render();

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($reportofferid && Validators::isId($reportofferid)) {
            $offer = $this->offerRepository->findOffer((int) $reportofferid, ['id', 'name']);
            if (! $offer) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_offer_id'));
            }
            $arr = $offer->toArray();
            $form = view('moderation._confirm', [
                'pre' => (string) __('legacy/report.text_are_you_sure_offer'),
                'kind' => 'offer',
                'id' => $arr['id'] ?? 0,
                'name' => (string) ($arr['name'] ?? ''),
                'mid' => (string) __('legacy/report.text_to_staff'),
                'extraNote' => null,
                'field' => 'takereportofferid',
            ])->render();

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_action'));

    }

    public function reports(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! $this->permissionChecker->userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $repo = $this->moderationRepository;
        $count = $repo->countReports();
        if (! $count) {
            return $this->legacyAbortResponse(__('legacy/reports.std_oho'), __('legacy/reports.std_no_report'));
        }

        $perpage = 10;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, '/web/reports?');

        $reportRows = $repo->getReports($offset, $rpp);

        $rows = [];
        foreach ($reportRows as $reportRow) {
            $row = (array) $reportRow;

            $row['dealtwith'] = (bool) $row['dealtwith'];
            if ($row['dealtwith']) {
                $row['dealtbyHtml'] = UserDisplay::username($row['dealtby']);
            }

            $type = '';
            $reporting = '';
            $typeEnum = is_numeric($row['type']) ? ReportType::tryFrom((int) $row['type']) : null;
            $typeString = $typeEnum?->stringValue() ?? (string) $row['type'];
            switch ($typeString) {
                case 'torrent':
                    $type = __('legacy/reports.text_torrent');
                    $torrent = $this->torrentRepository->findById((int) $row['reportid'], ['id', 'name']);
                    if (! $torrent) {
                        $reporting = (string) (__('legacy/reports.text_torrent_does_not_exist'));
                    } else {
                        $arr = $torrent->toArray();
                        $reporting = view('moderation._reporting_cell', [
                            'kind' => 'torrent',
                            'id' => $arr['id'] ?? 0,
                            'name' => (string) ($arr['name'] ?? ''),
                        ])->render();
                    }
                    break;
                case 'user':
                    $type = __('legacy/reports.text_user');
                    $userId = $this->userRepository->existsById((int) $row['reportid']) ? (int) $row['reportid'] : null;
                    if (! $userId) {
                        $reporting = (string) (__('legacy/reports.text_user_does_not_exist'));
                    } else {
                        $reporting = view('moderation._reporting_cell', [
                            'kind' => 'user',
                            'userHtml' => UserDisplay::username($userId),
                        ])->render();
                    }
                    break;
                case 'offer':
                    $type = __('legacy/reports.text_offer');
                    $offer = $this->offerRepository->findOffer((int) $row['reportid'], ['id', 'name']);
                    if (! $offer) {
                        $reporting = (string) (__('legacy/reports.text_offer_does_not_exist'));
                    } else {
                        $arr = $offer->toArray();
                        $reporting = view('moderation._reporting_cell', [
                            'kind' => 'offer',
                            'id' => $arr['id'] ?? 0,
                            'name' => (string) ($arr['name'] ?? ''),
                        ])->render();
                    }
                    break;
                case 'post':
                    $type = __('legacy/reports.text_forum_post');
                    $arr = $this->moderationRepository->getForumPost((int) $row['reportid']);
                    if ($arr === null) {
                        $reporting = (string) (__('legacy/reports.text_post_does_not_exist'));
                    } else {
                        $reporting = view('moderation._reporting_cell', [
                            'kind' => 'post',
                            'id' => $row['reportid'],
                            'topicid' => $arr['topicid'],
                            'subject' => (string) $arr['subject'],
                            'userHtml' => UserDisplay::username($arr['postuserid']),
                        ])->render();
                    }
                    break;
                case 'comment':
                    $type = __('legacy/reports.text_comment');
                    $comment = $this->commentRepository->findById((int) $row['reportid'], ['id', 'user', 'torrent', 'offer']);
                    if (! $comment) {
                        $reporting = (string) (__('legacy/reports.text_comment_does_not_exist'));
                    } else {
                        $arr = $comment->toArray();
                        if ($arr['torrent'] ?? null) {
                            $name = $this->torrentRepository->getNameById((int) $arr['torrent']);
                            $url = '/web/details/'.$arr['torrent'].'#cid'.$row['reportid'];
                            $of = __('legacy/reports.text_of_torrent');
                        } elseif ($arr['offer'] ?? null) {
                            $name = $this->offerRepository->getOfferName((int) $arr['offer']);
                            $url = '/web/offers?id='.$arr['offer'].'&off_details=1#cid'.$row['reportid'];
                            $of = __('legacy/reports.text_of_offer');
                        } else {
                            $name = '';
                            $url = '';
                            $of = 'unknown';
                        }
                        $reporting = view('moderation._reporting_cell', [
                            'kind' => 'comment',
                            'id' => $row['reportid'],
                            'of' => (string) $of,
                            'url' => $url,
                            'name' => (string) $name,
                            'userHtml' => UserDisplay::username((int) ($arr['user'] ?? 0)),
                        ])->render();
                    }
                    break;
            }

            $row['type_label'] = $type;
            $row['reporting'] = SafeHtml::fromTrustedHtml($reporting);
            $row['added_formatted'] = SafeHtml::fromTrustedHtml((string) Time::format($row['added']));
            $row['reporterHtml'] = UserDisplay::username($row['addedby']);
            $rows[] = $row;
        }

        return $this->legacyPage($request, 'reports', true, [
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }
}
