<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Cheater;
use App\Models\Comment;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ModerationRepository;
use App\Support\Format;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\Ratio;
use App\Support\SupportContext;
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
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $staffmemClass = defined('UC_STAFFMEM') ? \constant('UC_STAFFMEM') : (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);

        $langReport = (array) SupportContext::getGlobal('lang_report', []);
        $cache = SupportContext::getCache();

        $reportofferid = (int) (SupportContext::getQuery('reportofferid') ?? 0);
        $user = (int) (SupportContext::getQuery('user') ?? 0);
        $commentid = (int) (SupportContext::getQuery('commentid') ?? 0);
        $torrent = (int) (SupportContext::getQuery('torrent') ?? 0);
        $forumpost = (int) (SupportContext::getQuery('forumpost') ?? 0);

        $takeuser = (int) (SupportContext::getPost('takeuser') ?? 0);
        $takecommentid = (int) (SupportContext::getPost('takecommentid') ?? 0);
        $taketorrent = (int) (SupportContext::getPost('taketorrent') ?? 0);
        $takeforumpost = (int) (SupportContext::getPost('takeforumpost') ?? 0);
        $takereportofferid = (int) (SupportContext::getPost('takereportofferid') ?? 0);
        $takereason = trim((string) SupportContext::getPost('reason'));

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
                $msg = ($langReport['std_cannot_report'] ?? 'Cannot report staff member ') . UserClass::name((int) $arr['class'], false, true, true);
                return $this->legacyAbortResponse($langReport['std_sorry'] ?? 'Sorry', $msg);
            }

            $form = ($langReport['text_are_you_sure_user'] ?? 'Are you sure you want to report user ') . UserDisplay::username($user) . ($langReport['text_to_staff'] ?? ' to staff?') . '<br />' . ($langReport['text_not_for_leechers'] ?? '') . '<br />' . ($langReport['text_reason_note'] ?? '') . '<br /><form method=post action=report.php><input type=hidden name=takeuser value="' . htmlspecialchars((string) $user) . '">' . ($langReport['text_reason_is'] ?? 'Reason: ') . '<input type=text style="width: 200px" name=reason><input type=submit value="' . ($langReport['submit_confirm'] ?? 'Confirm') . '"></form>';
            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($torrent && Validators::isId($torrent)) {
            $name = Torrent::query()->where('id', $torrent)->value('name');
            if (! $name) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_torrent_id'] ?? 'Invalid torrent ID.');
            }
            $form = ($langReport['text_are_you_sure_torrent'] ?? 'Are you sure you want to report torrent ') . '<a href=details.php?id=' . htmlspecialchars((string) $torrent) . '><b>' . htmlspecialchars((string) $name) . '</b></a>' . ($langReport['text_to_staff'] ?? ' to staff?') . '<br />' . ($langReport['text_reason_note'] ?? '') . '<br /><form method=post action=report.php><input type=hidden name=taketorrent value="' . htmlspecialchars((string) $torrent) . '">' . ($langReport['text_reason_is'] ?? 'Reason: ') . '<input type=text style="width: 200px" name=reason><input type=submit value="' . ($langReport['submit_confirm'] ?? 'Confirm') . '"></form>';
            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($forumpost && Validators::isId($forumpost)) {
            $arr = app(ModerationRepository::class)->getForumPost($forumpost);
            if ($arr === null) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_post_id'] ?? 'Invalid post ID.');
            }
            $form = ($langReport['text_are_you_sure_post'] ?? 'Are you sure you want to report post #') . $forumpost . ($langReport['text_of_topic'] ?? ' of topic ') . '<b><a href="forums.php?action=viewtopic&topicid=' . $arr['topicid'] . '&page=p' . htmlspecialchars((string) $forumpost) . '#' . htmlspecialchars((string) $forumpost) . '">' . htmlspecialchars($arr['subject']) . '</a></b>' . ($langReport['text_by'] ?? ' by ') . UserDisplay::username($arr['postuserid']) . ($langReport['text_to_staff'] ?? ' to staff?') . '<br />' . ($langReport['text_reason_note'] ?? '') . '<br /><form method=post action=report.php><input type=hidden name=takeforumpost value="' . htmlspecialchars((string) $forumpost) . '">' . ($langReport['text_reason_is'] ?? 'Reason: ') . '<input type=text style="width: 200px" name=reason><input type=submit value="' . ($langReport['submit_confirm'] ?? 'Confirm') . '"></form>';
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
                $url = 'details.php?id=' . $arr['torrent'] . '#' . $commentid;
                $of = $langReport['text_of_torrent'] ?? ' of torrent ';
            } elseif ($arr['offer']) {
                $name = Offer::query()->where('id', $arr['offer'])->value('name');
                $url = 'offers.php?id=' . $arr['offer'] . '&off_details=1#' . $commentid;
                $of = $langReport['text_of_offer'] ?? ' of offer ';
            } else {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_orphaned_comment'] ?? 'Orphaned comment.');
            }
            $form = ($langReport['text_are_you_sure_comment'] ?? 'Are you sure you want to report comment #') . $commentid . $of . '<b><a href="' . $url . '">' . htmlspecialchars((string) $name) . '</a></b>' . ($langReport['text_by'] ?? ' by ') . UserDisplay::username($arr['user']) . ($langReport['text_to_staff'] ?? ' to staff?') . '<br />' . ($langReport['text_reason_note'] ?? '') . '<br /><form method=post action=report.php><input type=hidden name=takecommentid value="' . htmlspecialchars((string) $commentid) . '">' . ($langReport['text_reason_is'] ?? 'Reason: ') . '<input type=text style="width: 200px" name=reason><input type=submit value="' . ($langReport['submit_confirm'] ?? 'Confirm') . '"></form>';
            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        if ($reportofferid && Validators::isId($reportofferid)) {
            $offer = Offer::query()->where('id', $reportofferid)->first(['id', 'name']);
            if (! $offer) {
                return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_offer_id'] ?? 'Invalid offer ID.');
            }
            $arr = $offer->toArray();
            $form = ($langReport['text_are_you_sure_offer'] ?? 'Are you sure you want to report offer ') . '<a href="offers.php?id=' . $arr['id'] . '&off_details=1"><b>' . htmlspecialchars($arr['name']) . '</b></a>' . ($langReport['text_to_staff'] ?? ' to staff?') . '<br />' . ($langReport['text_reason_note'] ?? '') . '<br /><form method=post action=report.php><input type=hidden name=takereportofferid value="' . htmlspecialchars((string) $reportofferid) . '">' . ($langReport['text_reason_is'] ?? 'Reason: ') . '<input type=text style="width: 200px" name=reason><input type=submit value="' . ($langReport['submit_confirm'] ?? 'Confirm') . '"></form>';
            return $this->legacyAbortResponse($langReport['std_are_you_sure'] ?? 'Are you sure?', $form, false);
        }

        return $this->legacyAbortResponse($langReport['std_error'] ?? 'Error', $langReport['std_invalid_action'] ?? 'Invalid action.');

    }

    public function reports(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langReports = (array) SupportContext::getGlobal('lang_reports', []);

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
                $row['dealtwith_html'] = '<font color=green>' . ($langReports['text_yes'] ?? 'Yes') . '</font> - ' . UserDisplay::username($row['dealtby']);
            } else {
                $row['dealtwith_html'] = '<font color=red>' . ($langReports['text_no'] ?? 'No') . '</font>';
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
                        $reporting = '<a href=details.php?id=' . $arr['id'] . '>' . htmlspecialchars($arr['name']) . '</a>';
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
                        $reporting = '<a href="offers.php?id=' . $arr['id'] . '&off_details=1">' . htmlspecialchars($arr['name']) . '</a>';
                    }
                    break;
                case 'post':
                    $type = $langReports['text_forum_post'] ?? 'Forum post';
                    $arr = app(ModerationRepository::class)->getForumPost((int) $row['reportid']);
                    if ($arr === null) {
                        $reporting = $langReports['text_post_does_not_exist'] ?? 'Post does not exist';
                    } else {
                        $reporting = ($langReports['text_post_id'] ?? 'Post #') . $row['reportid'] . ($langReports['text_of_topic'] ?? ' of topic ') . '<b><a href="forums.php?action=viewtopic&topicid=' . $arr['topicid'] . '&page=p' . htmlspecialchars((string) $row['reportid']) . '#pid' . htmlspecialchars((string) $row['reportid']) . '">' . htmlspecialchars($arr['subject']) . '</a></b>' . ($langReports['text_by'] ?? ' by ') . UserDisplay::username($arr['postuserid']);
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
                            $url = 'details.php?id=' . $arr['torrent'] . '#cid' . $row['reportid'];
                            $of = $langReports['text_of_torrent'] ?? ' of torrent ';
                        } elseif ($arr['offer']) {
                            $name = Offer::query()->where('id', $arr['offer'])->value('name');
                            $url = 'offers.php?id=' . $arr['offer'] . '&off_details=1#cid' . $row['reportid'];
                            $of = $langReports['text_of_offer'] ?? ' of offer ';
                        } else {
                            $name = '';
                            $url = '';
                            $of = 'unknown';
                        }
                        $reporting = ($langReports['text_comment_id'] ?? 'Comment #') . $row['reportid'] . $of . '<b><a href="' . $url . '">' . htmlspecialchars((string) $name) . '</a></b>' . ($langReports['text_by'] ?? ' by ') . UserDisplay::username($arr['user']);
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

    public function bans(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;

        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $username = (string) ($curUser['username'] ?? '');

        $remove = (int) (SupportContext::getQuery('remove') ?? 0);
        if (SupportContext::getQuery('remove') !== null && Validators::isId($remove)) {
            app(ModerationRepository::class)->deleteBan($remove);
            Log::writeWithContext("Ban ".htmlspecialchars((string) $remove)." was removed by {$currentUserId} ({$username})", 'mod');
        }

        if ($request->isMethod('post')) {
            $first = trim((string) SupportContext::getPost('first'));
            $last = trim((string) SupportContext::getPost('last'));
            $comment = trim((string) SupportContext::getPost('comment'));
            if ($first === '' || $last === '' || $comment === '') {
                return $this->legacyAbortResponse('Error', 'Missing form data.');
            }
            $firstlong = ip2long($first);
            $lastlong = ip2long($last);
            if ($firstlong === false || $lastlong === false || $firstlong === -1 || $lastlong === -1) {
                return $this->legacyAbortResponse('Error', 'Bad IP address.');
            }
            app(ModerationRepository::class)->createBan([
                'added' => date('Y-m-d H:i:s'),
                'addedby' => $currentUserId,
                'first' => $firstlong,
                'last' => $lastlong,
                'comment' => $comment,
            ]);

            return redirect($request->getRequestUri());
        }

        $bans = app(ModerationRepository::class)->getBans();

        return $this->legacyPage($request, 'bans', true, [
            'bans' => $bans,
            'canAdd' => UserDisplay::currentClass() >= $administratorClass,
            'currentUserId' => $currentUserId,
        ]);

    }

    public function cheaterbox(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langFunctions = (array) SupportContext::getGlobal('lang_functions', []);
        $langCheaterbox = (array) SupportContext::getGlobal('lang_cheaterbox', []);

        $delcheater = (array) SupportContext::getPost('delcheater');
        if (! empty($delcheater) && (SupportContext::getPost('setdealt') || SupportContext::getPost('delete'))) {
            $ids = array_map('intval', array_filter($delcheater, 'is_numeric'));
            if (empty($ids)) {
                return $this->legacyAbortResponse('Error', $langFunctions['select_at_least_one_record'] ?? 'Select at least one record.');
            }

            if (SupportContext::getPost('setdealt')) {
                Cheater::query()->whereIn('id', $ids)->where('dealtwith', 0)->update([
                    'dealtwith' => 1,
                    'dealtby' => $currentUserId,
                ]);
            } else {
                Cheater::query()->whereIn('id', $ids)->delete();
            }

            $cache = SupportContext::getCache();
            $cache?->delete_value('staff_new_cheater_count', true);
        }

        $count = Cheater::query()->count();
        if (! $count) {
            return $this->legacyAbortResponse(
                $langCheaterbox['std_oho'] ?? 'Oho',
                $langCheaterbox['std_no_suspect_detected'] ?? 'No suspect detected.'
            );
        }

        $perpage = 50;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, 'cheaterbox.php?');

        $rawRows = Cheater::query()
            ->orderBy('dealtwith')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($rpp)
            ->get();

        $rows = [];
        foreach ($rawRows as $row) {
            $r = (array) $row->getAttributes();
            $torrentName = Torrent::query()->where('id', $r['torrentid'])->value('name');
            if ($torrentName) {
                $r['torrent'] = '<a href=details.php?id='.$r['torrentid'].'>'.htmlspecialchars($torrentName).'</a>';
            } else {
                $r['torrent'] = $langCheaterbox['text_torrent_does_not_exist'] ?? 'Torrent does not exist';
            }

            $upspeed = ($r['uploaded'] > 0 ? $r['uploaded'] / $r['anctime'] : 0);
            $lespeed = ($r['downloaded'] > 0 ? $r['downloaded'] / $r['anctime'] : 0);
            $r['uploaded_str'] = Format::size($r['uploaded']) . ($upspeed ? ' @ '.Format::size($upspeed).'/s' : '');
            $r['downloaded_str'] = Format::size($r['downloaded']) . ($lespeed ? ' @ '.Format::size($lespeed).'/s' : '');
            $r['added_formatted'] = Time::format($r['added']);

            if ($r['dealtwith']) {
                $r['dealtwith_html'] = '<font color=green>'.($langCheaterbox['text_yes'] ?? 'Yes').'</font> - '.UserDisplay::username($r['dealtby']);
            } else {
                $r['dealtwith_html'] = '<font color=red>'.($langCheaterbox['text_no'] ?? 'No').'</font>';
            }

            $rows[] = $r;
        }

        return $this->legacyPage($request, 'cheaterbox', true, [
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

    public function cheaters(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $class = (int) (SupportContext::getQuery('c') ?? 0);
        if (! Validators::isUserClass($class - 2)) {
            $class = 0;
        }

        $ratio = SupportContext::getQuery('r');
        if (! Validators::isId($ratio) || (int) $ratio < 1 || (int) $ratio > 7) {
            $ratio = '';
        } else {
            $ratio = (int) $ratio;
        }

        $baseQuery = \App\Models\User::query()
            ->where('enabled', 1)
            ->where('downloaded', '>', 0)
            ->where('uploaded', '>', 0);

        if ($class > 2) {
            $baseQuery->where('class', '<', $class - 1);
        }
        if ($ratio > 1) {
            $baseQuery->whereRaw('(uploaded / downloaded) > ?', [$ratio - 1]);
        }

        $agg = (clone $baseQuery)->selectRaw('COUNT(*) as cnt, MIN(cheat) as minc, MAX(cheat) as maxc')->first();
        $top = min(100, (int) ($agg->cnt ?? 0));
        $min = (float) ($agg->minc ?? 0);
        $max = (float) ($agg->maxc ?? 0);

        $page = (int) (SupportContext::getQuery('page') ?? 1);
        $pages = (int) ceil($top / 20);
        if ($page < 1) {
            $page = 1;
        } elseif ($pages > 0 && $page > $pages) {
            $page = $pages;
        }

        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager(20, $top, 'cheaters.php?');

        $rawRows = $baseQuery->orderByDesc('cheat')->offset($offset)->limit($rpp)->get()->map(fn ($r) => (array) $r->getAttributes())->all();

        $rows = [];
        foreach ($rawRows as $arr) {
            if ($arr['added'] === '0000-00-00 00:00:00' || $arr['added'] === null) {
                $arr['added'] = '-';
                $joindate = 'N/A';
                $age = 1;
            } else {
                $timestamp = strtotime($arr['added']);
                $joindate = \App\Support\Format::getElapsedTime($timestamp) . ' ago';
                $age = time() - $timestamp;
                if ($age < 1) {
                    $age = 1;
                }
            }

            if ($arr['downloaded'] > 0) {
                $ratioValue = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                $arr['ratio_html'] = '<font color=' . \App\Support\Ratio::color($ratioValue) . '>' . $ratioValue . '</font>';
            } elseif ($arr['uploaded'] > 0) {
                $arr['ratio_html'] = 'Inf.';
            } else {
                $arr['ratio_html'] = '---';
            }

            $arr['joindate'] = $joindate;
            $arr['upload_speed'] = \App\Support\Format::size($arr['uploaded'] / $age) . 'ps';
            $arr['download_speed'] = \App\Support\Format::size($arr['downloaded'] / $age) . 'ps';
            $arr['cheat_spread'] = (string) ceil(($arr['cheat'] - $min) / max(1, ($max - $min)) * 100) . '%';

            $rows[] = $arr;
        }

        $classOptions = [];
        for ($i = 2; ; ++$i) {
            $name = \App\Support\UserClass::name($i - 2);
            if ($name === '') {
                break;
            }
            $classOptions[] = ['value' => $i, 'label' => $name];
        }

        return $this->legacyPage($request, 'cheaters', true, [
            'class' => $class,
            'ratio' => $ratio,
            'top' => $top,
            'min' => $min,
            'max' => $max,
            'page' => $page,
            'pages' => $pages,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'classOptions' => $classOptions,
        ]);

    }

    public function iphistory(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langIphistory = (array) SupportContext::getGlobal('lang_iphistory', []);

        $userid = (int) (SupportContext::getQuery('id') ?? 0);
        if (! Validators::isId($userid)) {
            return $this->legacyAbortResponse($langIphistory['std_error'] ?? 'Error', $langIphistory['std_invalid_id'] ?? 'Invalid ID.');
        }

        $username = User::query()->where('id', $userid)->value('username');
        if (! $username) {
            return $this->legacyAbortResponse($langIphistory['error'] ?? 'Error', $langIphistory['text_user_not_found'] ?? 'User not found.');
        }

        $repo = app(ModerationRepository::class);
        $perpage = 20;
        $countrows = $repo->countIplogDistinct($userid) + 1;
        $order = (string) (SupportContext::getQuery('order') ?? '');

        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $countrows, "iphistory.php?id={$userid}&order={$order}&");

        $rawRows = $repo->getIphistoryRows($userid, $offset, $rpp);

        $rows = [];
        foreach ($rawRows as $row) {
            $arr = (array) $row;
            $ip = $arr['ip'] ?? '';
            $addr = '';
            $ipshow = '';
            if ($ip) {
                $dom = @gethostbyaddr($ip);
                if ($dom === $ip || @gethostbyname($dom) !== $ip) {
                    $addr = $langIphistory['text_not_available'] ?? 'N/A';
                } else {
                    $addr = $dom;
                }

                $usersIp = $repo->getUserIdsByIp($ip);
                $iplogIp = $repo->getIplogUserIdsByIp($ip);
                $ipcount = count(array_unique(array_merge($usersIp, $iplogIp)));

                if ($ipcount > 1) {
                    $ipshow = '<a href="ipsearch.php?ip=' . $ip . '">' . $ip . '</a> <b>(<font class=\'striking\'>' . ($langIphistory['text_duplicate'] ?? 'Duplicate') . '</font>)</b>';
                } else {
                    $ipshow = '<a href="ipsearch.php?ip=' . $ip . '">' . $ip . '</a>';
                }
            }
            $arr['addr'] = $addr;
            $arr['ipshow'] = $ipshow;
            $arr['date'] = Time::format($arr['access']);
            $rows[] = $arr;
        }

        return $this->legacyPage($request, 'iphistory', true, [
            'userid' => $userid,
            'username' => $username,
            'countrows' => $countrows,
            'perpage' => $rpp,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

    public function ipcheck(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $curUser = SupportContext::getUser() ?? [];

        if (UserDisplay::currentClass() < $moderatorClass && ($curUser['guard'] ?? '') !== 'yes') {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $title = 'Duplicate IP users';

        $duplicateIps = app(ModerationRepository::class)->getDuplicateIps();

        $rows = [];
        foreach ($duplicateIps as $dupRow) {
            $ras = (array) $dupRow;
            if ((int) $ras['dupl'] <= 1) {
                break;
            }
            $users = User::query()
                ->where('ip', $ras['ip'])
                ->orderBy('id')
                ->get(['id', 'username', 'email', 'added', 'last_access', 'downloaded', 'uploaded', 'ip', 'warned', 'donor', 'enabled'])
                ->map(fn ($r) => (array) $r->getAttributes())
                ->all();

            if (count($users) > 1) {
                $peerCounts = app(ModerationRepository::class)->getPeerCountsByIp($ras['ip']);

                foreach ($users as $arr) {
                    if ($arr['added'] === '0000-00-00 00:00:00' || $arr['added'] === null) {
                        $arr['added'] = '-';
                    }
                    if ($arr['last_access'] === '0000-00-00 00:00:00' || $arr['last_access'] === null) {
                        $arr['last_access'] = '-';
                    }

                    if ((float) $arr['downloaded'] != 0) {
                        $ratioValue = number_format((float) $arr['uploaded'] / (float) $arr['downloaded'], 3);
                        $ratioHtml = '<font color=' . Ratio::color($ratioValue) . '>' . $ratioValue . '</font>';
                    } else {
                        $ratioHtml = '---';
                    }

                    $arr['uploaded_str'] = Format::size((float) $arr['uploaded']);
                    $arr['downloaded_str'] = Format::size((float) $arr['downloaded']);
                    $arr['added_date'] = substr((string) $arr['added'], 0, 10);
                    $arr['last_access_date'] = substr((string) $arr['last_access'], 0, 10);
                    $arr['ratio_html'] = $ratioHtml;
                    $arr['peer_count'] = $peerCounts[$arr['id']] ?? 0;
                    $rows[] = $arr;
                }
            }
        }

        return $this->legacyPage($request, 'ipcheck', true, [
            'title' => $title,
            'rows' => $rows,
        ]);

    }

    public function ipsearch(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $langIpsearch = (array) SupportContext::getGlobal('lang_ipsearch', []);

        $ip = htmlspecialchars(trim((string) (SupportContext::getQuery('ip') ?? '')));
        if ($ip !== '' && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->legacyAbortResponse($langIpsearch['std_error'] ?? 'Error', $langIpsearch['std_invalid_ip'] ?? 'Invalid IP.');
        }

        $mask = trim((string) (SupportContext::getQuery('mask') ?? ''));
        $singleIp = ($mask === '' || $mask === '255.255.255.255');
        $addr = '';

        if ($ip !== '') {
            if ($singleIp) {
                $dom = @gethostbyaddr($ip);
                if ($dom !== $ip && @gethostbyname($dom) === $ip) {
                    $addr = $dom;
                }
            } else {
                $regex = "/^(((1?\d{1,2})|(2[0-4]\d)|(25[0-5]))(\.\b|$)){4}$/";
                if (substr($mask, 0, 1) === '/') {
                    $n = (int) substr($mask, 1);
                    if ($n < 0 || $n > 32) {
                        return $this->legacyAbortResponse($langIpsearch['std_error'] ?? 'Error', $langIpsearch['std_invalid_subnet_mask'] ?? 'Invalid subnet mask.');
                    }
                    $mask = long2ip((int) (pow(2, 32) - pow(2, 32 - $n)));
                } elseif (! preg_match($regex, $mask)) {
                    return $this->legacyAbortResponse($langIpsearch['std_error'] ?? 'Error', $langIpsearch['std_invalid_subnet_mask'] ?? 'Invalid subnet mask.');
                }
                $addr = 'Mask: ' . $mask;
            }
        }

        $repo = app(ModerationRepository::class);

        $count = 0;
        $rows = [];
        $pagertop = '';
        $pagerbottom = '';

        if ($ip !== '') {
            $order = (string) (SupportContext::getQuery('order') ?? '');
            $count = $repo->countIpsearch($ip, $mask, $singleIp);

            if ($count > 0) {
                $perpage = 20;
                [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, "ipsearch.php?ip={$ip}&mask={$mask}&order={$order}&");

                $users = $repo->getIpsearchRows($ip, $mask, $singleIp, $order, $offset, $rpp);

                foreach ($users as $userRow) {
                    $user = (array) $userRow;
                    if ($user['added'] === '0000-00-00 00:00:00' || $user['added'] === null) {
                        $added = $langIpsearch['text_not_available'] ?? 'N/A';
                    } else {
                        $added = Time::format($user['added']);
                    }
                    if ($user['last_access'] === '0000-00-00 00:00:00' || $user['last_access'] === null) {
                        $lastaccess = $langIpsearch['text_not_available'] ?? 'N/A';
                    } else {
                        $lastaccess = Time::format($user['last_access']);
                    }

                    $ipstr = $user['last_ip'] ? (string) $user['last_ip'] : ($langIpsearch['text_not_available'] ?? 'N/A');
                    $iphistory = $repo->countIplogDistinctByUser((int) $user['id']);
                    $invitedBy = ((int) $user['invited_by']) > 0 ? UserDisplay::username((int) $user['invited_by']) : ($langIpsearch['text_not_available'] ?? 'N/A');

                    $rows[] = [
                        'id' => (int) $user['id'],
                        'username_html' => UserDisplay::username((int) $user['id']),
                        'ipstr' => $ipstr,
                        'lastaccess' => $lastaccess,
                        'iphistory' => $iphistory,
                        'access' => Time::format($user['access']),
                        'added' => $added,
                        'invited_by' => $invitedBy,
                    ];
                }
            }
        }

        return $this->legacyPage($request, 'ipsearch', true, [
            'ip' => $ip,
            'mask' => $mask,
            'addr' => $addr,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'lang_ipsearch' => $langIpsearch,
        ]);

    }

}