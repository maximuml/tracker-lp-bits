<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Services\CleanupService;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class SystemController extends LegacyController
{
    public function delacctadmin(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'delacctadmin', true);

    }

    public function deletedisabled(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'deletedisabled', true);

    }

    public function massmail(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'massmail', true);

    }

    public function takeamountupload(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeamountupload', true);

    }

    public function takeinvite(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/takeinvite.php' . ($qs ? '?' . $qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $lockName = sprintf('takeinvite:%s', $currentUserId);
        $lock = new \Nexus\Database\NexusLock($lockName, 10);
        if (! $lock->get()) {
            $errMsg = \App\Support\Locale::trans('nexus.do_not_repeat', [], null);
            return $this->legacyAbortResponse($errMsg, $errMsg);
        }

        try {
            \App\Support\LegacyAuth::registrationCheckFromContext('invitesystem', true, false);

            $userRep = new \App\Repositories\UserRepository();
            try {
                $sendText = $userRep->getInviteBtnText($currentUserId);
            } catch (\Exception $exception) {
                $lang = (array) SupportContext::getGlobal('lang_takeinvite', []);
                return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $exception->getMessage());
            }

            $email = \App\Support\Input::unescape(htmlspecialchars(trim((string) SupportContext::getPost('email'))));
            $email = \App\Support\Email::sanitizeForDisplay($email);
            $preRegisterUsername = (string) SupportContext::getPost('pre_register_username');
            $isPreRegisterEmailAndUsername = \App\Support\Config\SiteConfig::current()->system->isInvitePreEmailAndUsername();
            $lang = (array) SupportContext::getGlobal('lang_takeinvite', []);

            if (strlen($preRegisterUsername) > 12) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_username_too_long'] ?? 'Username too long.');
            }
            if (! $email) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_enter_email'] ?? 'Enter an email.');
            }
            if (! \App\Support\Email::isWellFormed($email)) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_invalid_email_address'] ?? 'Invalid email.');
            }

            $body = str_replace('<br />', '<br />', nl2br(trim(strip_tags((string) SupportContext::getPost('body')))));
            if (! $body) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_enter_personal_message'] ?? 'Enter a message.');
            }

            if ($isPreRegisterEmailAndUsername) {
                if (empty($preRegisterUsername)) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        \App\Support\Locale::trans('invite.require_pre_register_username', [], null)
                    );
                }
                if (! \App\Support\Validators::isUsername($preRegisterUsername)) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        \App\Support\Locale::trans('user.username_invalid', ['username' => $preRegisterUsername], null)
                    );
                }
                if (\App\Models\User::query()->where('username', $preRegisterUsername)->exists()) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        \App\Support\Locale::trans('user.username_already_exists', ['username' => $preRegisterUsername], null)
                    );
                }
            }

            if (\App\Models\User::query()->where('email', $email)->count() > 0) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_email_address'] . htmlspecialchars($email) . $lang['std_is_in_use']);
            }
            if (\App\Models\Invite::query()->where('invitee', $email)->count() > 0) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_invitation_already_sent_to'] . htmlspecialchars($email) . $lang['std_await_user_registeration']);
            }

            $hashPost = (string) SupportContext::getPost('hash');
            if ($hashPost === '') {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_select_invite'] ?? 'Select an invite.');
            }

            $hashRecord = null;
            $timeNow = (int) SupportContext::getGlobal('TIMENOW', time());
            if ($hashPost === 'permanent') {
                $hash = md5(mt_rand(1, 10000) . $curUser['username'] . $timeNow . $curUser['passhash']);
            } else {
                $hashRecord = \App\Models\Invite::query()->where('inviter', $currentUserId)->where('hash', $hashPost)->first();
                if (! $hashRecord instanceof \App\Models\Invite) {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['hash_not_exists'] ?? 'Hash does not exist.');
                }
                if ($hashRecord->invitee !== '') {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', 'hash ' . $lang['std_is_in_use']);
                }
                if ($hashRecord->expired_at !== null && $hashRecord->expired_at->lt(now())) {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['hash_expired'] ?? 'Hash expired.');
                }
                $hash = $hashPost;
            }

            $siteName = \App\Models\Setting::getSiteName();
            $title = $siteName . $lang['mail_tilte'];
            $signupUrl = \App\Support\Url::schemeAndHost(\App\Support\Url::isSecure()) . "/signup.php?type=invite&invitenumber=$hash";
            $mailTwo = sprintf($lang['mail_two'], $siteName, $siteName);
            $mailFour = sprintf($lang['mail_four'], $siteName);
            $reportMail = (string) SupportContext::getGlobal('REPORTMAIL', '');
            $mailSix = sprintf($lang['mail_six'], $reportMail, $siteName);
            $inviteTimeout = (string) SupportContext::getGlobal('invite_timeout', '');

            $message = $lang['mail_one'] . $curUser['username'] . $mailTwo . PHP_EOL
                . '<b><a href="javascript:void(null)" onclick="window.open(' . $signupUrl . ')">' . $lang['mail_here'] . '</a></b><br />' . PHP_EOL
                . $signupUrl . PHP_EOL
                . '<br />' . $lang['mail_three'] . $inviteTimeout . $mailFour . $curUser['username'] . $lang['mail_five'] . '<br />' . PHP_EOL
                . $body . PHP_EOL
                . '<br /><br />' . $mailSix;

            $sendResult = \App\Support\Mail::sentLegacy(
                $email,
                $siteName,
                (string) SupportContext::getGlobal('SITEEMAIL', ''),
                $title,
                $message,
                'invitesignup',
                false,
                false,
                '',
                'UTF-8'
            );

            if ($sendResult === true) {
                $update = [
                    'invitee' => $email,
                    'time_invited' => now(),
                    'valid' => 1,
                ];
                if ($isPreRegisterEmailAndUsername) {
                    $update['pre_register_email'] = $email;
                    $update['pre_register_username'] = $preRegisterUsername;
                }

                if ($hashRecord instanceof \App\Models\Invite) {
                    $hashRecord->update($update);
                } else {
                    $insert = [
                        'inviter' => $currentUserId,
                        'invitee' => $email,
                        'hash' => $hash,
                        'time_invited' => now()->toDateTimeString(),
                    ] + $update;
                    unset($insert['valid']); // already included
                    \App\Models\Invite::query()->insert($insert);
                    \App\Models\User::query()->where('id', $currentUserId)->decrement('invites');
                }
            }
        } finally {
            $lock->release();
        }

        return redirect('/invite.php?id=' . $currentUserId . '&sent=1');
    }

    public function takeupdate(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/takeupdate.php' . ($qs ? '?' . $qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $delreport = (array) SupportContext::getPost('delreport');
        if (empty($delreport)) {
            $langFunctions = (array) SupportContext::getGlobal('lang_functions', []);
            return $this->legacyAbortResponse('Error', $langFunctions['select_at_least_one_record'] ?? 'Select at least one record.');
        }

        $delreportIds = array_map('intval', array_filter($delreport, 'is_numeric'));
        if (empty($delreportIds)) {
            return $this->legacyAbortResponse('Error', 'Invalid report ids.');
        }

        $cache = SupportContext::getCache();
        if (SupportContext::getPost('setdealt')) {
            NexusDB::table('reports')
                ->whereIn('id', $delreportIds)
                ->where('dealtwith', 0)
                ->update(['dealtwith' => 1, 'dealtby' => $currentUserId]);
            $cache?->delete_value('staff_new_report_count', true);
        } elseif (SupportContext::getPost('delete')) {
            NexusDB::table('reports')->whereIn('id', $delreportIds)->delete();
            $cache?->delete_value('staff_new_report_count', true);
            $cache?->delete_value('staff_report_count', true);
        }

        return redirect('/reports.php');
    }

    public function docleanup(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->runFull($request->boolean('forceall'), true),
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );

    }

    public function mailtest(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'mailtest', true);

    }

    public function mysqlStats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'mysql_stats', true);

    }

    public function cron(Request $request): Response
    {

        return \response(
            app(CleanupService::class)->triggerCron(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );

    }

    public function incrementBulk(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'increment-bulk', true);

    }

    public function maxlogin(Request $request): Response|RedirectResponse|View
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $action = (string) (SupportContext::getPost('action') ?? SupportContext::getQuery('action') ?? 'showlist');
        $action = htmlspecialchars($action);
        $id = (int) (SupportContext::getPost('id') ?? SupportContext::getQuery('id') ?? 0);
        $update = (string) (SupportContext::getPost('update') ?? SupportContext::getQuery('update') ?? '');

        if ($action === 'ban' || $action === 'unban' || $action === 'delete' || $action === 'edit' || $action === 'save') {
            if (! Validators::isId($id)) {
                return $this->legacyAbortResponse('Error', 'Invalid ID');
            }
        }

        if ($action === 'ban') {
            NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'yes']);
            return redirect('maxlogin.php?update=Ban');
        }

        if ($action === 'unban') {
            NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'no']);
            return redirect('maxlogin.php?update=Unban');
        }

        if ($action === 'delete') {
            NexusDB::table('loginattempts')->where('id', $id)->delete();
            return redirect('maxlogin.php?update=Delete');
        }

        if ($action === 'save') {
            $attempts = (int) SupportContext::getPost('attempts');
            $type = (string) SupportContext::getPost('type');
            $banned = (string) SupportContext::getPost('banned');
            if (! is_numeric($attempts) || $attempts < 0) {
                return $this->legacyAbortResponse('Error', 'Invalid attempts');
            }
            NexusDB::table('loginattempts')->where('id', $id)->update([
                'attempts' => $attempts,
                'type' => $type,
                'banned' => $banned,
            ]);
            if (SupportContext::getPost('returnto')) {
                return redirect((string) SupportContext::getPost('returnto'));
            }
            return redirect('maxlogin.php?update=Edit');
        }

        $order = (string) (SupportContext::getQuery('order') ?? '');
        $orderColumn = match ($order) {
            'ip' => 'ip',
            'added' => 'added',
            'attempts' => 'attempts',
            'type' => 'type',
            'status' => 'banned',
            default => 'id',
        };

        $perpage = 50;
        $msg = $update ? '<h3><b>' . htmlspecialchars($update) . ' Successful!</b></h3>' : '';

        if ($action === 'searchip') {
            $ip = (string) (SupportContext::getPost('ip') ?? '');
            $search = NexusDB::table('loginattempts')->where('ip', 'LIKE', '%' . $ip . '%')->get();
            $rows = [];
            foreach ($search as $attemptRow) {
                $arr = (array) $attemptRow;
                $user = User::query()->where('ip', $arr['ip'])->first(['id', 'username']);
                $a2 = $user ? $user->toArray() : [];
                $rows[] = [
                    'id' => $arr['id'],
                    'ip' => $arr['ip'],
                    'added' => $arr['added'],
                    'attempts' => $arr['attempts'],
                    'type' => $arr['type'],
                    'banned' => $arr['banned'],
                    'userId' => $a2['id'] ?? 0,
                    'username' => $a2['username'] ?? '',
                ];
            }

            return $this->legacyPage($request, 'maxlogin', true, [
                'action' => 'searchip',
                'msg' => $msg,
                'rows' => $rows,
                'editRow' => null,
            ]);
        }

        if ($action !== 'showlist' && $action !== 'edit') {
            return $this->legacyAbortResponse('Error', 'Invalid Action');
        }

        if ($action === 'edit') {
            $editRow = (array) NexusDB::table('loginattempts')->where('id', $id)->first();

            return $this->legacyPage($request, 'maxlogin', true, [
                'action' => 'edit',
                'msg' => $msg,
                'rows' => [],
                'editRow' => $editRow,
                'returnto' => SupportContext::getQuery('return') === 'yes' ? 'viewunbaniprequest.php' : '',
            ]);
        }

        $countrows = (int) NexusDB::table('loginattempts')->count() + 1;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $countrows, "maxlogin.php?order={$order}&");

        $loginAttempts = NexusDB::table('loginattempts')->orderByDesc($orderColumn)->offset($offset)->limit($rpp)->get();
        $rows = [];
        foreach ($loginAttempts as $attemptRow) {
            $arr = (array) $attemptRow;
            $user = User::query()->where('ip', $arr['ip'])->first(['id', 'username']);
            $a2 = $user ? $user->toArray() : [];
            $rows[] = [
                'id' => $arr['id'],
                'ip' => $arr['ip'],
                'added' => $arr['added'],
                'attempts' => $arr['attempts'],
                'type' => $arr['type'],
                'banned' => $arr['banned'],
                'userId' => $a2['id'] ?? 0,
                'username' => $a2['username'] ?? '',
            ];
        }

        return $this->legacyPage($request, 'maxlogin', true, [
            'action' => 'showlist',
            'msg' => $msg,
            'rows' => $rows,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'countrows' => $countrows,
            'perpage' => $rpp,
            'editRow' => null,
        ]);

    }

    public function setlistLookup(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageRaw($request, 'setlist_lookup', true);

    }

    public function takeIncrementBulk(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'take-increment-bulk', true);

    }

}