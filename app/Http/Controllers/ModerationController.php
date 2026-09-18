<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Enums\ReportType;
use App\Models\Comment;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ModerationRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use App\Support\Permissions;
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
    public function __construct(
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
            $userRow = User::query()->where('id', $user)->first(['username', 'class']);
            if (! $userRow) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_user_id'));
            }
            $arr = $userRow->toArray();
            if ((int) $arr['class'] >= $staffmemClass) {
                $msg = (__('legacy/report.std_cannot_report')).UserClass::name((int) $arr['class'], false, true, true);

                return $this->legacyAbortResponse(__('legacy/report.std_sorry'), $msg);
            }

            $form = (__('legacy/report.text_are_you_sure_user')).UserDisplay::username($user).(__('legacy/report.text_to_staff')).'<br />'.(__('legacy/report.text_not_for_leechers')).'<br />'.(__('legacy/report.text_reason_note')).'<br /><form method=post action=report.php><input type=hidden name=takeuser value="'.htmlspecialchars((string) $user).'">'.(__('legacy/report.text_reason_is')).'<input type=text style="width: 200px" name=reason><input type=submit value="'.(__('legacy/report.submit_confirm')).'"></form>';

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($torrent && Validators::isId($torrent)) {
            $name = Torrent::query()->where('id', $torrent)->value('name');
            if (! $name) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_torrent_id'));
            }
            $form = (__('legacy/report.text_are_you_sure_torrent')).'<a href=details.php?id='.htmlspecialchars((string) $torrent).'><b>'.htmlspecialchars((string) $name).'</b></a>'.(__('legacy/report.text_to_staff')).'<br />'.(__('legacy/report.text_reason_note')).'<br /><form method=post action=report.php><input type=hidden name=taketorrent value="'.htmlspecialchars((string) $torrent).'">'.(__('legacy/report.text_reason_is')).'<input type=text style="width: 200px" name=reason><input type=submit value="'.(__('legacy/report.submit_confirm')).'"></form>';

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($forumpost && Validators::isId($forumpost)) {
            $arr = $this->moderationRepository->getForumPost($forumpost);
            if ($arr === null) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_post_id'));
            }
            $form = (__('legacy/report.text_are_you_sure_post')).$forumpost.(__('legacy/report.text_of_topic')).'<b><a href="forums.php?action=viewtopic&topicid='.$arr['topicid'].'&page=p'.htmlspecialchars((string) $forumpost).'#'.htmlspecialchars((string) $forumpost).'">'.htmlspecialchars($arr['subject']).'</a></b>'.(__('legacy/report.text_by')).UserDisplay::username($arr['postuserid']).(__('legacy/report.text_to_staff')).'<br />'.(__('legacy/report.text_reason_note')).'<br /><form method=post action=report.php><input type=hidden name=takeforumpost value="'.htmlspecialchars((string) $forumpost).'">'.(__('legacy/report.text_reason_is')).'<input type=text style="width: 200px" name=reason><input type=submit value="'.(__('legacy/report.submit_confirm')).'"></form>';

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($commentid && Validators::isId($commentid)) {
            $comment = Comment::query()->where('id', $commentid)->first(['id', 'user', 'torrent', 'offer']);
            if (! $comment) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_comment_id'));
            }
            $arr = $comment->toArray();
            if ($arr['torrent']) {
                $name = Torrent::query()->where('id', $arr['torrent'])->value('name');
                $url = 'details.php?id='.$arr['torrent'].'#'.$commentid;
                $of = __('legacy/report.text_of_torrent');
            } elseif ($arr['offer']) {
                $name = Offer::query()->where('id', $arr['offer'])->value('name');
                $url = 'offers.php?id='.$arr['offer'].'&off_details=1#'.$commentid;
                $of = __('legacy/report.text_of_offer');
            } else {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_orphaned_comment'));
            }
            $form = (__('legacy/report.text_are_you_sure_comment')).$commentid.$of.'<b><a href="'.$url.'">'.htmlspecialchars((string) $name).'</a></b>'.(__('legacy/report.text_by')).UserDisplay::username($arr['user']).(__('legacy/report.text_to_staff')).'<br />'.(__('legacy/report.text_reason_note')).'<br /><form method=post action=report.php><input type=hidden name=takecommentid value="'.htmlspecialchars((string) $commentid).'">'.(__('legacy/report.text_reason_is')).'<input type=text style="width: 200px" name=reason><input type=submit value="'.(__('legacy/report.submit_confirm')).'"></form>';

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        if ($reportofferid && Validators::isId($reportofferid)) {
            $offer = Offer::query()->where('id', $reportofferid)->first(['id', 'name']);
            if (! $offer) {
                return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_offer_id'));
            }
            $arr = $offer->toArray();
            $form = (__('legacy/report.text_are_you_sure_offer')).'<a href="offers.php?id='.$arr['id'].'&off_details=1"><b>'.htmlspecialchars($arr['name']).'</b></a>'.(__('legacy/report.text_to_staff')).'<br />'.(__('legacy/report.text_reason_note')).'<br /><form method=post action=report.php><input type=hidden name=takereportofferid value="'.htmlspecialchars((string) $reportofferid).'">'.(__('legacy/report.text_reason_is')).'<input type=text style="width: 200px" name=reason><input type=submit value="'.(__('legacy/report.submit_confirm')).'"></form>';

            return $this->legacyAbortResponse(__('legacy/report.std_are_you_sure'), $form, false);
        }

        return $this->legacyAbortResponse(__('legacy/report.std_error'), __('legacy/report.std_invalid_action'));

    }

    public function reports(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $repo = $this->moderationRepository;
        $count = $repo->countReports();
        if (! $count) {
            return $this->legacyAbortResponse(__('legacy/reports.std_oho'), __('legacy/reports.std_no_report'));
        }

        $perpage = 10;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, 'reports.php?');

        $reportRows = $repo->getReports($offset, $rpp);

        $rows = [];
        foreach ($reportRows as $reportRow) {
            $row = (array) $reportRow;

            if ($row['dealtwith']) {
                $row['dealtwith_html'] = SafeHtml::fromTrustedHtml('<font color=green>'.(__('legacy/reports.text_yes')).'</font> - '.UserDisplay::username($row['dealtby']));
            } else {
                $row['dealtwith_html'] = SafeHtml::fromTrustedHtml('<font color=red>'.(__('legacy/reports.text_no')).'</font>');
            }

            $type = '';
            $reporting = '';
            $typeEnum = is_numeric($row['type']) ? ReportType::tryFrom((int) $row['type']) : null;
            $typeString = $typeEnum?->stringValue() ?? (string) $row['type'];
            switch ($typeString) {
                case 'torrent':
                    $type = __('legacy/reports.text_torrent');
                    $torrent = Torrent::query()->where('id', $row['reportid'])->first(['id', 'name']);
                    if (! $torrent) {
                        $reporting = (string) (__('legacy/reports.text_torrent_does_not_exist'));
                    } else {
                        $arr = $torrent->toArray();
                        $reporting = '<a href=details.php?id='.$arr['id'].'>'.htmlspecialchars($arr['name']).'</a>';
                    }
                    break;
                case 'user':
                    $type = __('legacy/reports.text_user');
                    $userId = User::query()->where('id', $row['reportid'])->value('id');
                    if (! $userId) {
                        $reporting = (string) (__('legacy/reports.text_user_does_not_exist'));
                    } else {
                        $reporting = UserDisplay::username($userId);
                    }
                    break;
                case 'offer':
                    $type = __('legacy/reports.text_offer');
                    $offer = Offer::query()->where('id', $row['reportid'])->first(['id', 'name']);
                    if (! $offer) {
                        $reporting = (string) (__('legacy/reports.text_offer_does_not_exist'));
                    } else {
                        $arr = $offer->toArray();
                        $reporting = '<a href="offers.php?id='.$arr['id'].'&off_details=1">'.htmlspecialchars($arr['name']).'</a>';
                    }
                    break;
                case 'post':
                    $type = __('legacy/reports.text_forum_post');
                    $arr = $this->moderationRepository->getForumPost((int) $row['reportid']);
                    if ($arr === null) {
                        $reporting = (string) (__('legacy/reports.text_post_does_not_exist'));
                    } else {
                        $reporting = (string) (__('legacy/reports.text_post_id')).$row['reportid'].(__('legacy/reports.text_of_topic')).'<b><a href="forums.php?action=viewtopic&topicid='.$arr['topicid'].'&page=p'.htmlspecialchars((string) $row['reportid']).'#pid'.htmlspecialchars((string) $row['reportid']).'">'.htmlspecialchars($arr['subject']).'</a></b>'.(__('legacy/reports.text_by')).UserDisplay::username($arr['postuserid']);
                    }
                    break;
                case 'comment':
                    $type = __('legacy/reports.text_comment');
                    $comment = Comment::query()->where('id', $row['reportid'])->first(['id', 'user', 'torrent', 'offer']);
                    if (! $comment) {
                        $reporting = (string) (__('legacy/reports.text_comment_does_not_exist'));
                    } else {
                        $arr = $comment->toArray();
                        if ($arr['torrent']) {
                            $name = Torrent::query()->where('id', $arr['torrent'])->value('name');
                            $url = 'details.php?id='.$arr['torrent'].'#cid'.$row['reportid'];
                            $of = __('legacy/reports.text_of_torrent');
                        } elseif ($arr['offer']) {
                            $name = Offer::query()->where('id', $arr['offer'])->value('name');
                            $url = 'offers.php?id='.$arr['offer'].'&off_details=1#cid'.$row['reportid'];
                            $of = __('legacy/reports.text_of_offer');
                        } else {
                            $name = '';
                            $url = '';
                            $of = 'unknown';
                        }
                        $reporting = (string) (__('legacy/reports.text_comment_id')).$row['reportid'].$of.'<b><a href="'.$url.'">'.htmlspecialchars((string) $name).'</a></b>'.(__('legacy/reports.text_by')).UserDisplay::username($arr['user']);
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
