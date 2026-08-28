<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Comment;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ModerationRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
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
    public function report(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $staffmemClass = defined('UC_STAFFMEM') ? \constant('UC_STAFFMEM') : (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);

        $langReport = (array) app(Globals::class)->get('lang_report', []);
        $cache = app(LegacyRedisCache::class);

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

        $repo = app(ModerationRepository::class);
        $doTakeReport = function (int $reportid, string $type, string $reason) use ($currentUserId, $langReport, $cache, $repo): Response {
            if (! Validators::isId($reportid) || $reason === '') {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_missing_reason'] ?? 'Missing reason.');
            }

            if ($repo->reportExists($currentUserId, $reportid, $type)) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_already_reported_this'] ?? 'You already reported this.');
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

            return $this->legacyAbortResponse($langReport['std_message'] ?? 'Message', $langReport['std_successfully_reported'] ?? 'Report submitted successfully.', false);
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
                return $this->legacyAbortResponse($langReport['std_sorry'] ?? 'Sorry', $langReport['std_cannot_report_oneself'] ?? 'Cannot report yourself.');
            }
            $userRow = User::query()->where('id', $user)->first(['username', 'class']);
            if (! $userRow) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_user_id'] ?? 'Invalid user ID.');
            }
            $arr = $userRow->toArray();
            if ((int) $arr['class'] >= $staffmemClass) {
                $msg = ($langReport['std_cannot_report'] ?? 'Cannot report staff member ').UserClass::name((int) $arr['class'], false, true, true);

                return $this->legacyAbortResponse($langReport['std_sorry'] ?? 'Sorry', $msg);
            }

            $form = ($langReport['text_are_you_sure_user'] ?? 'Are you sure you want to report user ').UserDisplay::username($user).($langReport['text_to_staff'] ?? ' to staff?').'<br />'.($langReport['text_not_for_leechers'] ?? '').'<br />'.($langReport['text_reason_note'] ?? '').'<br /><form method=post action=report.php><input type=hidden name=takeuser value="'.htmlspecialchars((string) $user).'">'.($langReport['text_reason_is'] ?? 'Reason: ').'<input type=text style="width: 200px" name=reason><input type=submit value="'.($langReport['submit_confirm'] ?? 'Confirm').'"></form>';

            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($torrent && Validators::isId($torrent)) {
            $name = Torrent::query()->where('id', $torrent)->value('name');
            if (! $name) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_torrent_id'] ?? 'Invalid torrent ID.');
            }
            $form = ($langReport['text_are_you_sure_torrent'] ?? 'Are you sure you want to report torrent ').'<a href=details.php?id='.htmlspecialchars((string) $torrent).'><b>'.htmlspecialchars((string) $name).'</b></a>'.($langReport['text_to_staff'] ?? ' to staff?').'<br />'.($langReport['text_reason_note'] ?? '').'<br /><form method=post action=report.php><input type=hidden name=taketorrent value="'.htmlspecialchars((string) $torrent).'">'.($langReport['text_reason_is'] ?? 'Reason: ').'<input type=text style="width: 200px" name=reason><input type=submit value="'.($langReport['submit_confirm'] ?? 'Confirm').'"></form>';

            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($forumpost && Validators::isId($forumpost)) {
            $arr = app(ModerationRepository::class)->getForumPost($forumpost);
            if ($arr === null) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_post_id'] ?? 'Invalid post ID.');
            }
            $form = ($langReport['text_are_you_sure_post'] ?? 'Are you sure you want to report post #').$forumpost.($langReport['text_of_topic'] ?? ' of topic ').'<b><a href="forums.php?action=viewtopic&topicid='.$arr['topicid'].'&page=p'.htmlspecialchars((string) $forumpost).'#'.htmlspecialchars((string) $forumpost).'">'.htmlspecialchars($arr['subject']).'</a></b>'.($langReport['text_by'] ?? ' by ').UserDisplay::username($arr['postuserid']).($langReport['text_to_staff'] ?? ' to staff?').'<br />'.($langReport['text_reason_note'] ?? '').'<br /><form method=post action=report.php><input type=hidden name=takeforumpost value="'.htmlspecialchars((string) $forumpost).'">'.($langReport['text_reason_is'] ?? 'Reason: ').'<input type=text style="width: 200px" name=reason><input type=submit value="'.($langReport['submit_confirm'] ?? 'Confirm').'"></form>';

            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($commentid && Validators::isId($commentid)) {
            $comment = Comment::query()->where('id', $commentid)->first(['id', 'user', 'torrent', 'offer']);
            if (! $comment) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_comment_id'] ?? 'Invalid comment ID.');
            }
            $arr = $comment->toArray();
            if ($arr['torrent']) {
                $name = Torrent::query()->where('id', $arr['torrent'])->value('name');
                $url = 'details.php?id='.$arr['torrent'].'#'.$commentid;
                $of = $langReport['text_of_torrent'] ?? ' of torrent ';
            } elseif ($arr['offer']) {
                $name = Offer::query()->where('id', $arr['offer'])->value('name');
                $url = 'offers.php?id='.$arr['offer'].'&off_details=1#'.$commentid;
                $of = $langReport['text_of_offer'] ?? ' of offer ';
            } else {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_orphaned_comment'] ?? 'Orphaned comment.');
            }
            $form = ($langReport['text_are_you_sure_comment'] ?? 'Are you sure you want to report comment #').$commentid.$of.'<b><a href="'.$url.'">'.htmlspecialchars((string) $name).'</a></b>'.($langReport['text_by'] ?? ' by ').UserDisplay::username($arr['user']).($langReport['text_to_staff'] ?? ' to staff?').'<br />'.($langReport['text_reason_note'] ?? '').'<br /><form method=post action=report.php><input type=hidden name=takecommentid value="'.htmlspecialchars((string) $commentid).'">'.($langReport['text_reason_is'] ?? 'Reason: ').'<input type=text style="width: 200px" name=reason><input type=submit value="'.($langReport['submit_confirm'] ?? 'Confirm').'"></form>';

            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($reportofferid && Validators::isId($reportofferid)) {
            $offer = Offer::query()->where('id', $reportofferid)->first(['id', 'name']);
            if (! $offer) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_offer_id'] ?? 'Invalid offer ID.');
            }
            $arr = $offer->toArray();
            $form = ($langReport['text_are_you_sure_offer'] ?? 'Are you sure you want to report offer ').'<a href="offers.php?id='.$arr['id'].'&off_details=1"><b>'.htmlspecialchars($arr['name']).'</b></a>'.($langReport['text_to_staff'] ?? ' to staff?').'<br />'.($langReport['text_reason_note'] ?? '').'<br /><form method=post action=report.php><input type=hidden name=takereportofferid value="'.htmlspecialchars((string) $reportofferid).'">'.($langReport['text_reason_is'] ?? 'Reason: ').'<input type=text style="width: 200px" name=reason><input type=submit value="'.($langReport['submit_confirm'] ?? 'Confirm').'"></form>';

            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_action'] ?? 'Invalid action.');

    }

    public function reports(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langReports = (array) app(Globals::class)->get('lang_reports', []);

        $repo = app(ModerationRepository::class);
        $count = $repo->countReports();
        if (! $count) {
            return $this->legacyAbortResponse($langReports['std_oho'] ?? 'Oho', $langReports['std_no_report'] ?? 'No report.');
        }

        $perpage = 10;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, 'reports.php?');

        $reportRows = $repo->getReports($offset, $rpp);

        $rows = [];
        foreach ($reportRows as $reportRow) {
            $row = (array) $reportRow;

            if ($row['dealtwith']) {
                $row['dealtwith_html'] = '<font color=green>'.($langReports['text_yes'] ?? 'Yes').'</font> - '.UserDisplay::username($row['dealtby']);
            } else {
                $row['dealtwith_html'] = '<font color=red>'.($langReports['text_no'] ?? 'No').'</font>';
            }

            $type = '';
            $reporting = '';
            switch ($row['type']) {
                case 'torrent':
                    $type = $langReports['text_torrent'] ?? 'Torrent';
                    $torrent = Torrent::query()->where('id', $row['reportid'])->first(['id', 'name']);
                    if (! $torrent) {
                        $reporting = $langReports['text_torrent_does_not_exist'] ?? 'Torrent does not exist';
                    } else {
                        $arr = $torrent->toArray();
                        $reporting = '<a href=details.php?id='.$arr['id'].'>'.htmlspecialchars($arr['name']).'</a>';
                    }
                    break;
                case 'user':
                    $type = $langReports['text_user'] ?? 'User';
                    $userId = User::query()->where('id', $row['reportid'])->value('id');
                    if (! $userId) {
                        $reporting = $langReports['text_user_does_not_exist'] ?? 'User does not exist';
                    } else {
                        $reporting = UserDisplay::username($userId);
                    }
                    break;
                case 'offer':
                    $type = $langReports['text_offer'] ?? 'Offer';
                    $offer = Offer::query()->where('id', $row['reportid'])->first(['id', 'name']);
                    if (! $offer) {
                        $reporting = $langReports['text_offer_does_not_exist'] ?? 'Offer does not exist';
                    } else {
                        $arr = $offer->toArray();
                        $reporting = '<a href="offers.php?id='.$arr['id'].'&off_details=1">'.htmlspecialchars($arr['name']).'</a>';
                    }
                    break;
                case 'post':
                    $type = $langReports['text_forum_post'] ?? 'Forum post';
                    $arr = app(ModerationRepository::class)->getForumPost((int) $row['reportid']);
                    if ($arr === null) {
                        $reporting = $langReports['text_post_does_not_exist'] ?? 'Post does not exist';
                    } else {
                        $reporting = ($langReports['text_post_id'] ?? 'Post #').$row['reportid'].($langReports['text_of_topic'] ?? ' of topic ').'<b><a href="forums.php?action=viewtopic&topicid='.$arr['topicid'].'&page=p'.htmlspecialchars((string) $row['reportid']).'#pid'.htmlspecialchars((string) $row['reportid']).'">'.htmlspecialchars($arr['subject']).'</a></b>'.($langReports['text_by'] ?? ' by ').UserDisplay::username($arr['postuserid']);
                    }
                    break;
                case 'comment':
                    $type = $langReports['text_comment'] ?? 'Comment';
                    $comment = Comment::query()->where('id', $row['reportid'])->first(['id', 'user', 'torrent', 'offer']);
                    if (! $comment) {
                        $reporting = $langReports['text_comment_does_not_exist'] ?? 'Comment does not exist';
                    } else {
                        $arr = $comment->toArray();
                        if ($arr['torrent']) {
                            $name = Torrent::query()->where('id', $arr['torrent'])->value('name');
                            $url = 'details.php?id='.$arr['torrent'].'#cid'.$row['reportid'];
                            $of = $langReports['text_of_torrent'] ?? ' of torrent ';
                        } elseif ($arr['offer']) {
                            $name = Offer::query()->where('id', $arr['offer'])->value('name');
                            $url = 'offers.php?id='.$arr['offer'].'&off_details=1#cid'.$row['reportid'];
                            $of = $langReports['text_of_offer'] ?? ' of offer ';
                        } else {
                            $name = '';
                            $url = '';
                            $of = 'unknown';
                        }
                        $reporting = ($langReports['text_comment_id'] ?? 'Comment #').$row['reportid'].$of.'<b><a href="'.$url.'">'.htmlspecialchars((string) $name).'</a></b>'.($langReports['text_by'] ?? ' by ').UserDisplay::username($arr['user']);
                    }
                    break;
            }

            $row['type_label'] = $type;
            $row['reporting'] = $reporting;
            $row['added_formatted'] = Time::format($row['added']);
            $rows[] = $row;
        }

        return $this->legacyPage($request, 'reports', true, [
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }
}
