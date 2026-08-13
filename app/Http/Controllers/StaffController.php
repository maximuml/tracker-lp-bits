<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Setting;
use App\Models\StaffMessage;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use App\Repositories\MessageRepository;
use App\Repositories\ModtaskRepository;
use App\Repositories\ToolRepository;
use App\Support\Cache;
use App\Support\Country;
use App\Support\Format;
use App\Support\Hooks;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Network;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\User as SupportUser;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class StaffController extends LegacyController
{
    public function modtask(Request $request): Response|RedirectResponse
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        if (! Permission::can(PermissionEnum::MANAGE_USER_BASIC_INFO, User::findOrFail($currentUserId))) {
            Log::writeWithContext(
                'User ' . ($currentUser['username'] ?? '') . " (id: {$currentUserId}) is hacking user's profile. IP : " . Network::clientIp(),
                'mod'
            );
            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        $action = (string) SupportContext::getPost('action');

        if ($action === 'confirmuser') {
            $userId = (int) SupportContext::getPost('userid');
            $confirm = (string) SupportContext::getPost('confirm');
            ModtaskRepository::confirmUser($userId, $confirm);
            return redirect($baseUrl . '/unco.php?status=1');
        }

        if ($action !== 'edituser') {
            return $this->legacyAbortResponse('Error', 'Invalid action.');
        }

        $userId = (int) SupportContext::getPost('userid');
        $userInfo = User::query()->findOrFail($userId);

        $class = $userInfo->class;
        $vipAdded = $userInfo->vip_added;
        $vipUntil = $userInfo->vip_until;

        $warned = (string) (SupportContext::getPost('warned') ?? '');
        $warnLength = (int) (SupportContext::getPost('warnlength') ?? 0);
        $warnPm = (string) (SupportContext::getPost('warnpm') ?? '');
        $title = (string) (SupportContext::getPost('title') ?? '');
        $avatar = (string) (SupportContext::getPost('avatar') ?? '');
        $signature = (string) (SupportContext::getPost('signature') ?? '');
        $enabled = (string) (SupportContext::getPost('enabled') ?? 'yes');
        $uploadpos = (string) (SupportContext::getPost('uploadpos') ?? 'yes');
        $downloadpos = (string) (SupportContext::getPost('downloadpos') ?? 'yes');
        $privacy = (string) (SupportContext::getPost('privacy') ?? 'normal');
        $forumpost = (string) (SupportContext::getPost('forumpost') ?? 'yes');
        $supportlang = (string) (SupportContext::getPost('supportlang') ?? '');
        $support = (string) (SupportContext::getPost('support') ?? 'no');
        $supportfor = (string) (SupportContext::getPost('supportfor') ?? '');
        $moviepicker = (string) (SupportContext::getPost('moviepicker') ?? 'no');
        $pickfor = (string) (SupportContext::getPost('pickfor') ?? '');
        $stafffor = (string) (SupportContext::getPost('staffduties') ?? '');

        if (! Validators::isId($userId) || ! SupportUser::isValidUserClass($class)) {
            return $this->legacyAbortResponse('Error', 'Bad user ID or class ID.');
        }
        if (UserDisplay::currentClass() <= $class) {
            return $this->legacyAbortResponse('Error', "You have no permission to change user's class to " . UserClass::name($class, false, false, true) . '. BTW, how do you get here?');
        }

        $arr = ModtaskRepository::getUserArray($userId);
        if ($arr === null) {
            Log::writeWithContext(
                'User ' . ($currentUser['username'] ?? '') . " (id: {$currentUserId}) is hacking user's profile. IP : " . Network::clientIp(),
                'mod'
            );
            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        $curEnabled = $arr['enabled'];
        $curParked = $arr['parked'];
        $curUploadpos = $arr['uploadpos'];
        $curDownloadpos = $arr['downloadpos'];
        $curForumpost = $arr['forumpost'];
        $curClass = $arr['class'];
        $curWarned = $arr['warned'];

        $updateset = [
            'stafffor' => $stafffor,
            'pickfor' => $pickfor,
            'picker' => $moviepicker,
            'uploadpos' => $uploadpos,
            'downloadpos' => $downloadpos,
            'forumpost' => $forumpost,
            'avatar' => $avatar,
            'signature' => $signature,
            'title' => $title,
            'support' => $support,
            'supportfor' => $supportfor,
            'supportlang' => $supportlang,
        ];

        $banLog = [];
        $userModifyLogs = [];

        if (Permission::can(PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO, User::findOrFail($currentUserId))) {
            $locale = Locale::userLocale($userId);
            $email = (string) (SupportContext::getPost('email') ?? '');
            $username = (string) (SupportContext::getPost('username') ?? '');
            $downloaded = (float) (SupportContext::getPost('downloaded') ?? 0);
            $uploaded = (float) (SupportContext::getPost('uploaded') ?? 0);
            $bonus = (float) (SupportContext::getPost('bonus') ?? 0);
            $invites = (int) (SupportContext::getPost('invites') ?? 0);

            if ($arr['email'] !== $email) {
                $updateset['email'] = $email;
                $modifyLog = "Email changed from {$arr['email']} to {$email} by {$currentUser['username']}.";
                Log::writeWithContext($modifyLog, 'alert');
                $userModifyLogs[] = $modifyLog;
                $subject = Locale::trans('user.msg_email_change', [], $locale);
                $msg = Locale::trans('user.msg_your_email_changed_from', [], $locale) . $arr['email'] . Locale::trans('user.msg_to_new', [], $locale) . $email . Locale::trans('user.msg_by', [], $locale) . $currentUser['username'];
                Message::add([
                    'sender' => 0,
                    'receiver' => $userId,
                    'subject' => $subject,
                    'msg' => $msg,
                    'added' => now(),
                ]);
            }

            if ($arr['username'] !== $username) {
                $updateset['username'] = $username;
                $userModifyLogs[] = "Username changed from {$arr['username']} to {$username} by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_username_change', [], $locale);
                $msg = Locale::trans('user.msg_your_username_changed_from', [], $locale) . $arr['username'] . Locale::trans('user.msg_to_new', [], $locale) . $username . Locale::trans('user.msg_by', [], $locale) . $currentUser['username'];
                Message::add([
                    'sender' => 0,
                    'receiver' => $userId,
                    'subject' => $subject,
                    'msg' => $msg,
                    'added' => now(),
                ]);
                UsernameChangeLog::query()->create([
                    'uid' => $arr['id'],
                    'operator' => $currentUser['username'],
                    'change_type' => UsernameChangeLog::CHANGE_TYPE_ADMIN,
                    'username_old' => $arr['username'],
                    'username_new' => $username,
                ]);
            }
        }

        $staffleaderClass = defined('UC_STAFFLEADER') ? \constant('UC_STAFFLEADER') : 0;
        if (UserDisplay::currentClass() == $staffleaderClass) {
            $locale = Locale::userLocale($userId);
            $donor = (string) SupportContext::getPost('donor');
            $donoruntil = SupportContext::getPost('donoruntil') ?: null;
            $donated = (float) (SupportContext::getPost('donated') ?? 0);
            $donatedCny = (float) (SupportContext::getPost('donated_cny') ?? 0);
            $thisDonatedUsd = $donated - (float) $arr['donated'];
            $thisDonatedCny = $donatedCny - (float) $arr['donated_cny'];
            $memo = htmlspecialchars((string) SupportContext::getPost('donation_memo'));

            if ($donated != (float) $arr['donated'] || $donatedCny != (float) $arr['donated_cny']) {
                ModtaskRepository::addFund($userId, $thisDonatedUsd, $thisDonatedCny, $memo);
                $updateset['donated'] = $donated;
                $updateset['donated_cny'] = $donatedCny;
            }

            $updateset['donor'] = $donor;
            $updateset['donoruntil'] = $donoruntil;

            $nowStr = date('Y-m-d H:i:s');
            if (($donor !== $arr['donor']) && (($donor === 'yes' && $donoruntil && $donoruntil >= $nowStr) || ($donor === 'no'))) {
                $subject = Locale::trans('user.msg_your_donor_status_changed', [], $locale);
                $msg = Locale::trans('user.msg_donor_status_changed_by', [], $locale) . $currentUser['username'];
                Message::add([
                    'sender' => 0,
                    'receiver' => $userId,
                    'subject' => $subject,
                    'msg' => $msg,
                    'added' => now(),
                ]);
                $userModifyLogs[] = "donor status changed by {$currentUser['username']}. Current donor status: {$donor}";
            }
        }

        if ($curClass >= UserDisplay::currentClass()) {
            Log::writeWithContext(
                'User ' . ($currentUser['username'] ?? '') . " (id: {$currentUserId}) is hacking user's profile. IP : " . Network::clientIp(),
                'mod'
            );
            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        if ($warned !== '' && $curWarned !== $warned) {
            $updateset['warned'] = $warned;
            $updateset['warneduntil'] = null;

            $locale = Locale::userLocale($userId);
            if ($warned === 'no') {
                $userModifyLogs[] = "Warning removed by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_warn_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_warning_removed_by', [], $locale) . $currentUser['username'] . '.';
            } else {
                $subject = '';
                $msg = '';
            }

            Message::add([
                'sender' => 0,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        } elseif ($warnLength > 0) {
            $locale = Locale::userLocale($userId);
            if ($warnLength == 255) {
                $userModifyLogs[] = "Warned by " . $currentUser['username'] . ".\nReason: {$warnPm}.";
                $msg = Locale::trans('user.msg_you_are_warned_by', [], $locale) . $currentUser['username'] . '.' . ($warnPm ? Locale::trans('user.msg_reason', [], $locale) . $warnPm : '');
                $updateset['warneduntil'] = null;
            } else {
                $warneduntil = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s')) + $warnLength * 604800);
                $dur = $warnLength . Locale::trans('user.msg_week', [], $locale) . ($warnLength > 1 ? Locale::trans('user.msg_s', [], $locale) : '');
                $msg = Locale::trans('user.msg_you_are_warned_for', [], $locale) . $dur . Locale::trans('user.msg_by', [], $locale) . $currentUser['username'] . '.' . ($warnPm ? Locale::trans('user.msg_reason', [], $locale) . $warnPm : '');
                $userModifyLogs[] = "Warned for {$dur} by " . $currentUser['username'] . ".Reason: {$warnPm}";
                $updateset['warneduntil'] = $warneduntil;
            }

            $subject = Locale::trans('user.msg_you_are_warned', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);

            $updateset['warned'] = 'yes';
            $updateset['lastwarned'] = now()->toDateTimeString();
            $updateset['warnedby'] = $currentUserId;
            $updateset['timeswarned'] = new \Illuminate\Database\Query\Expression('timeswarned + 1');
        }

        if (in_array($privacy, ['low', 'normal', 'strong'], true)) {
            $updateset['privacy'] = $privacy;
        }

        if (SupportContext::getPost('resetkey') !== null && SupportContext::getPost('resetkey') === 'yes') {
            $updateset['passkey'] = md5($arr['username'] . date('Y-m-d H:i:s') . $arr['passhash']);
        }

        if ($forumpost !== $curForumpost) {
            $locale = Locale::userLocale($userId);
            if ($forumpost === 'yes') {
                $userModifyLogs[] = "Posting enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_restored', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_you_can_post', [], $locale);
            } else {
                $userModifyLogs[] = "Posting disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_removed', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_probably_reason_two', [], $locale);
            }
            Message::add([
                'sender' => 0,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        if ($uploadpos !== $curUploadpos) {
            $locale = Locale::userLocale($userId);
            if ($uploadpos === 'yes') {
                $userModifyLogs[] = "Upload enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_upload_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_upload_rights_restored', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_you_upload_can_upload', [], $locale);
            } else {
                $userModifyLogs[] = "Upload disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_upload_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_upload_rights_removed', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_probably_reason_two', [], $locale);
            }
            Message::add([
                'sender' => 0,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        if ($downloadpos !== $curDownloadpos) {
            $locale = Locale::userLocale($userId);
            if ($downloadpos === 'yes') {
                $userModifyLogs[] = "Download enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_download_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_download_rights_restored', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_you_can_download', [], $locale);
            } else {
                $userModifyLogs[] = "Download disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_download_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_download_rights_removed', [], $locale) . $currentUser['username'] . Locale::trans('user.msg_probably_reason_three', [], $locale);
            }
            Message::add([
                'sender' => 0,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        ModtaskRepository::updateUser($userId, $updateset);

        if (! empty($banLog)) {
            UserBanLog::query()->insert($banLog);
        }
        if (! empty($userModifyLogs)) {
            $insert = [];
            foreach ($userModifyLogs as $log) {
                $insert[] = [
                    'user_id' => $userId,
                    'content' => $log,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
            UserModifyLog::query()->insert($insert);
        }

        Cache::clearUser($userId, $arr['passhash']);

        $returnto = (string) SupportContext::getPost('returnto');
        $prefix = \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure());
        return redirect($prefix . $baseUrl . '/' . ($returnto !== '' ? $returnto : 'userdetails.php?id=' . $userId));
    }

    public function staff(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langStaff = (array) SupportContext::getGlobal('lang_staff', []);
        $secs = 900;
        $dt = time() - $secs;

        $onlineImg = '<img class="button_online" src="pic/trans.gif" alt="online" title="' . ($langStaff['title_online'] ?? 'Online') . '" />';
        $offlineImg = '<img class="button_offline" src="pic/trans.gif" alt="offline" title="' . ($langStaff['title_offline'] ?? 'Offline') . '" />';
        $sendPmImg = '<img class="button_pm" src="pic/trans.gif" alt="pm" />';

        $buildUserRow = function (array $arr, string $extraKey = '') use ($dt, $onlineImg, $offlineImg, $sendPmImg, $langStaff): array {
            $countryrow = Country::rowWithContext($arr['country'] ?? 0) ?? ['flagpic' => '', 'name' => ''];
            $isOnline = strtotime((string) $arr['last_access']) > $dt;
            return [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'flag_html' => '<img width=24 height=15 src="pic/flag/' . $countryrow['flagpic'] . '" title="' . $countryrow['name'] . '" style="padding-bottom:1px;">',
                'online_html' => $isOnline ? $onlineImg : $offlineImg,
                'pm_html' => '<a href=sendmessage.php?receiver=' . (int) $arr['id'] . ' title="' . ($langStaff['title_send_pm'] ?? 'Send PM') . '">' . $sendPmImg . '</a>',
                'extra' => $extraKey ? ($arr[$extraKey] ?? '') : '',
            ];
        };

        $supportRows = User::query()
            ->where('support', 'yes')
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'supportlang', 'supportfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'supportfor'))
            ->all();

        $pickerRows = User::query()
            ->where('picker', 'yes')
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'pickfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'pickfor'))
            ->all();

        $forumModRows = [];
        $forumMods = NexusDB::table('forummods')
            ->leftJoin('users', 'forummods.userid', '=', 'users.id')
            ->orderBy('forummods.forumid')
            ->orderBy('forummods.userid')
            ->get(['forummods.userid AS userid', 'users.last_access', 'users.country'])
            ->unique('userid')
            ->values();

        foreach ($forumMods as $modRow) {
            $arr = (array) $modRow;
            $userId = (int) $arr['userid'];
            $forums = [];
            $forumRows = NexusDB::table('forums as f')
                ->leftJoin('forummods as fm', 'f.id', '=', 'fm.forumid')
                ->where('fm.userid', $userId)
                ->get(['f.id', 'f.name']);
            foreach ($forumRows as $forumRow) {
                $forums[] = '<a href=forums.php?action=viewforum&forumid=' . (int) $forumRow['id'] . '>' . htmlspecialchars((string) $forumRow['name']) . '</a>';
            }
            $base = $buildUserRow($arr, '');
            $base['forums_html'] = implode(', ', $forums);
            $forumModRows[] = $base;
        }

        $staffRows = [];
        $vipClass = defined('UC_VIP') ? \constant('UC_VIP') : 0;
        $staffUsers = User::query()
            ->where('class', '>', $vipClass)
            ->where('status', 'confirmed')
            ->orderByDesc('class')
            ->orderBy('username')
            ->get()
            ->map(fn ($r) => (array) $r->getAttributes())
            ->all();

        $currentClass = null;
        foreach ($staffUsers as $arr) {
            if ($currentClass !== $arr['class']) {
                $currentClass = $arr['class'];
                $staffRows[] = ['header' => true, 'class_name' => UserClass::name((int) $arr['class'], false, true, true)];
                $staffRows[] = ['subheader' => true];
            }
            $staffRows[] = $buildUserRow($arr, 'stafffor');
        }

        $vipRows = User::query()
            ->where('class', $vipClass)
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get()
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'stafffor'))
            ->all();

        return $this->legacyPage($request, 'staff', true, [
            'lang_staff' => $langStaff,
            'supportRows' => $supportRows,
            'pickerRows' => $pickerRows,
            'forumModRows' => $forumModRows,
            'staffRows' => $staffRows,
            'vipRows' => $vipRows,
            'siteName' => Setting::getSiteName(),
        ]);

    }

    public function staffbox(Request $request): View|RedirectResponse|Response
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $langStaffbox = (array) SupportContext::getGlobal('lang_staffbox', []);
        $langFunctions = SupportContext::getLangFunctions();

        $action = (string) (SupportContext::getQuery('action') ?? '');
        $queryString = (string) SupportContext::getServerValue('QUERY_STRING');
        $httpReferer = (string) SupportContext::getServerValue('HTTP_REFERER');

        $canAccessStaffMessage = function (array|int $msg) use ($currentUserId): void {
            if (Permission::can(PermissionEnum::STAFF_MEMBER, User::findOrFail($currentUserId))) {
                return;
            }
            if (is_numeric($msg)) {
                $msg = StaffMessage::query()->findOrFail((int) $msg)->toArray();
            }
            if (empty($msg['permission']) || ! in_array($msg['permission'], ToolRepository::listUserAllPermissions($currentUserId))) {
                abort(403, 'Permission denied.');
            }
        };

        if ($request->isMethod('post') && $action === 'takeanswer') {
            $receiver = (int) (SupportContext::getPost('receiver') ?? 0);
            $answeringto = (int) (SupportContext::getPost('answeringto') ?? 0);

            Validators::assertId($receiver, true);

            if (! User::query()->find($receiver)) {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_no_user_id'] ?? 'No user with that ID.');
            }

            $msg = trim((string) SupportContext::getPost('body'));
            if ($msg === '') {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_body_is_empty'] ?? 'Body is empty.');
            }

            $canAccessStaffMessage($answeringto);
            $subject = StaffMessage::query()->findOrFail($answeringto)->value('subject');

            Message::add([
                'sender' => $currentUserId,
                'receiver' => $receiver,
                'subject' => $subject,
                'added' => now(),
                'msg' => $msg,
            ]);

            StaffMessage::query()->where('id', $answeringto)->update(['answer' => $msg, 'answered' => 1, 'answeredby' => $currentUserId]);
            Cache::clearStaffMessage();

            return redirect('staffbox.php?action=viewpm&pmid=' . $answeringto);
        }

        if ($action === 'deletestaffmessage') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if ($id < 1) {
                return $this->legacyAbortResponse('Error', 'Invalid id');
            }
            $canAccessStaffMessage($id);
            StaffMessage::query()->where('id', $id)->delete();
            Cache::clearStaffMessage();
            $baseUrl = SupportContext::getGlobal('BASEURL', '');
            return redirect((string) $baseUrl . '/staffbox.php');
        }

        if ($action === 'setanswered') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $canAccessStaffMessage($id);
            StaffMessage::query()->where('id', $id)->update(['answered' => 1, 'answeredby' => $currentUserId]);
            Cache::clearStaffMessage();
            $return = (string) (SupportContext::getQuery('return') ?? '');
            return redirect('staffbox.php' . ($return !== '' ? '?' . $return : ''));
        }

        if ($request->isMethod('post') && $action === 'takecontactanswered') {
            $setAnswered = (array) SupportContext::getPost('setanswered');
            if (empty($setAnswered)) {
                return $this->legacyAbortResponse($langStaffbox['std_sorry'] ?? 'Sorry', \App\Support\Locale::trans('nexus.select_one_please', [], null));
            }
            $setDealt = SupportContext::getPost('setdealt') !== null;
            $delete = SupportContext::getPost('delete') !== null;

            $messages = StaffMessage::query()->whereIn('id', $setAnswered)->get();
            foreach ($messages as $message) {
                $canAccessStaffMessage($message->toArray());
                if ($setDealt) {
                    $message->update(['answered' => 1, 'answeredby' => $currentUserId]);
                } elseif ($delete) {
                    $message->delete();
                }
            }
            Cache::clearStaffMessage();
            return redirect('staffbox.php');
        }

        if ($action === 'viewpm') {
            $pmid = (int) (SupportContext::getQuery('pmid') ?? 0);
            $arr = StaffMessage::query()->findOrFail($pmid)->toArray();
            $canAccessStaffMessage($arr);

            $sender = Validators::isId($arr['sender']) ? UserDisplay::username($arr['sender']) : ($langStaffbox['text_system'] ?? 'System');
            $answeredby = UserDisplay::username($arr['answeredby']);

            return $this->legacyPage($request, 'staffbox', true, [
                'mode' => 'viewpm',
                'arr' => $arr,
                'sender' => $sender,
                'answeredby' => $answeredby,
                'lang_staffbox' => $langStaffbox,
            ]);
        }

        if ($action === 'answermessage') {
            $answeringto = (int) (SupportContext::getQuery('answeringto') ?? 0);
            $receiver = (int) (SupportContext::getQuery('receiver') ?? 0);

            Validators::assertId($receiver, true);

            $user = User::query()->find($receiver);
            if (! $user) {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_no_user_id'] ?? 'No user with that ID.');
            }

            $staffmsg = StaffMessage::query()->findOrFail($answeringto)->toArray();
            $canAccessStaffMessage($staffmsg);

            $returnTo = (string) (SupportContext::getQuery('returnto') ?? $httpReferer);

            return $this->legacyPage($request, 'staffbox', true, [
                'mode' => 'answermessage',
                'receiver' => $receiver,
                'answeringto' => $answeringto,
                'staffmsg' => $staffmsg,
                'returnTo' => $returnTo,
                'lang_staffbox' => $langStaffbox,
            ]);
        }

        // default list
        $query = MessageRepository::buildStaffMessageQuery($currentUserId);
        $count = (clone $query)->count();
        $perPage = 20;
        [$pagertop, $pagerbottom, , $offset, $pageSize] = \App\Support\Pagination::pager($perPage, $count, 'staffbox.php?');
        $rows = (clone $query)
            ->offset($offset)
            ->limit($pageSize)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        return $this->legacyPage($request, 'staffbox', true, [
            'mode' => 'list',
            'rows' => $rows,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'queryString' => $queryString,
            'lang_staffbox' => $langStaffbox,
            'lang_functions' => $langFunctions,
        ]);

    }

    public function staffmess(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $currentUser = SupportContext::getUser() ?? [];
        $classes = array_chunk(\App\Models\User::$classes, 4, true);

        return $this->legacyPage($request, 'staffmess', true, [
            'classes' => $classes,
            'body' => htmlspecialchars((string) SupportContext::getQuery('body')),
            'receiver' => (int) (SupportContext::getQuery('receiver') ?? 0),
            'username' => htmlspecialchars((string) ($currentUser['username'] ?? '')),
            'sent' => (int) (SupportContext::getQuery('sent') ?? 0),
        ]);
    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $currentUser = SupportContext::getUser() ?? [];
        $senderId = SupportContext::getPost('sender') === 'system' ? 0 : (int) ($currentUser['id'] ?? 0);
        $subject = trim((string) SupportContext::getPost('subject'));
        $msg = trim((string) SupportContext::getPost('msg'));

        if ($msg === '') {
            return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
        }

        $classIdsInput = SupportContext::getPost('clases');
        if (is_array($classIdsInput)) {
            foreach ($classIdsInput as $class) {
                $classId = (int) $class;
                if (! Validators::isId($classId) && $classId !== 0) {
                    return $this->legacyAbortResponse('Error', 'Invalid Class');
                }
            }
        } else {
            $classId = (int) $classIdsInput;
            if (! Validators::isId($classId) && $classId !== 0) {
                return $this->legacyAbortResponse('Error', 'Invalid Class');
            }
        }

        $size = 10000;
        $page = 1;
        $dt = now()->toDateTimeString();
        $conditions = [];
        $selectedClasses = (array) SupportContext::getPost('classes');
        if (! empty($selectedClasses)) {
            $classIds = array_map('intval', $selectedClasses);
            $conditions[] = 'class IN (' . implode(', ', $classIds) . ')';
        }
        $conditions = Hooks::applyFilter('role_query_conditions', $conditions, SupportContext::allPost());
        if (empty($conditions)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }
        $whereStr = implode(' OR ', $conditions);

        set_time_limit(300);

        while (true) {
            $offset = ($page - 1) * $size;
            $rows = NexusDB::table('users')
                ->whereRaw("($whereStr)")
                ->where('enabled', 'yes')
                ->where('status', 'confirmed')
                ->offset($offset)
                ->limit($size)
                ->get(['id']);

            if ($rows->isEmpty()) {
                break;
            }

            $msgRecords = [];
            foreach ($rows as $dat) {
                $msgRecords[] = [
                    'sender' => $senderId,
                    'receiver' => $dat->id,
                    'added' => $dt,
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }
            Message::query()->insert($msgRecords);
            $page++;
        }

        return redirect('staffmess.php?sent=1');
    }

    public function contactstaff(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'contactstaff', true, [
            'lang_contactstaff' => (array) SupportContext::getGlobal('lang_contactstaff', []),
        ]);

    }

    public function takecontact(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser() ?? [];
        $langTakecontact = (array) SupportContext::getGlobal('lang_takecontact', []);

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_method'] ?? 'Method not allowed.');
        }

        $msg = trim((string) SupportContext::getPost('body'));
        $subject = trim((string) SupportContext::getPost('subject'));

        if ($msg === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_enter_something'] ?? 'Please enter something.');
        }
        if ($subject === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_define_subject'] ?? 'Please define a subject.');
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $timeNow = (int) SupportContext::getGlobal('TIMENOW', time());

        if (UserDisplay::currentClass() < $moderatorClass) {
            $last = $curUser['last_staffmsg'] ?? null;
            if ($last !== null && strtotime((string) $last) > ($timeNow - 60)) {
                $secs = 60 - ($timeNow - strtotime((string) $last));
                return $this->legacyAbortResponse(
                    $langTakecontact['std_error'] ?? 'Error',
                    ($langTakecontact['std_message_flooding'] ?? 'Message flooding: wait ') . $secs . ($langTakecontact['std_second'] ?? ' second') . ($secs == 1 ? '' : ($langTakecontact['std_s'] ?? 's')) . ($langTakecontact['std_before_sending_pm'] ?? ' before sending PM.')
                );
            }
        }

        StaffMessage::add($currentUserId, $subject, $msg);

        User::query()->where('id', $currentUserId)->update(['last_staffmsg' => date('Y-m-d H:i:s')]);
        Cache::clearStaffMessage();

        $returnto = (string) SupportContext::getPost('returnto');
        if ($returnto !== '') {
            return redirect($returnto);
        }

        return $this->legacyPage($request, 'takecontact', true, [
            'lang_takecontact' => $langTakecontact,
        ]);
    }

    public function modrules(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the Rules, sorry.');
        }

        $act = (string) (SupportContext::getQuery('act') ?? 'list');

        if ($act === 'addsect' && $request->isMethod('post')) {
            $title = (string) SupportContext::getPost('title');
            $text = (string) SupportContext::getPost('text');
            $language = (int) SupportContext::getPost('language');
            NexusDB::table('rules')->insert([
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'edited' && $request->isMethod('post')) {
            $id = (int) (SupportContext::getPost('id') ?? 0);
            $title = (string) SupportContext::getPost('title');
            $text = (string) SupportContext::getPost('text');
            $language = (int) SupportContext::getPost('language');
            NexusDB::table('rules')->where('id', $id)->update([
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'del') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $sure = (int) (SupportContext::getQuery('sure') ?? 0);
            if (! $sure) {
                return $this->legacyAbortResponse('Delete Rule', 'You are about to delete a rule. Click <a class=altlink href=?act=del&id=' . $id . '&sure=1>here</a> if you are sure.', false);
            }
            NexusDB::table('rules')->where('id', $id)->delete();
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'newsect') {
            $langs = \App\Support\Locale::languageList('rule_lang', null);
            $defLang = (string) SupportContext::getGlobal('deflang', '');
            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'newsect',
                'langs' => $langs,
                'deflang' => $defLang,
            ]);
        }

        if ($act === 'edit') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $rule = (array) NexusDB::table('rules')->where('id', $id)->first();
            $langs = \App\Support\Locale::languageList('site_lang', null);
            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'edit',
                'rule' => $rule,
                'langs' => $langs,
            ]);
        }

        $rules = NexusDB::table('rules')
            ->leftJoin('language', 'rules.lang_id', '=', 'language.id')
            ->orderBy('lang_name')
            ->orderBy('rules.id')
            ->get(['rules.*', 'language.lang_name'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->legacyPage($request, 'modrules', true, [
            'mode' => 'list',
            'rows' => $rules,
        ]);

    }

    public function staffpanel(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Access denied!!!');
        }

        $langStaffpanel = (array) SupportContext::getGlobal('lang_staffpanel', []);

        $sysopPanels = [];
        $adminPanels = [];
        $modPanels = [];

        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : PHP_INT_MAX;
        $adminClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : PHP_INT_MAX;

        if (UserDisplay::currentClass() >= $sysopClass) {
            $sysopPanels = NexusDB::table('sysoppanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $adminClass) {
            $adminPanels = NexusDB::table('adminpanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $moderatorClass) {
            $modPanels = NexusDB::table('modpanel')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $this->legacyPage($request, 'staffpanel', true, [
            'lang_staffpanel' => $langStaffpanel,
            'sysopPanels' => $sysopPanels,
            'adminPanels' => $adminPanels,
            'modPanels' => $modPanels,
        ]);

    }

}