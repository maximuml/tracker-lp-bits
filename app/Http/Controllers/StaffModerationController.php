<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use App\Repositories\ModtaskRepository;
use App\Support\Cache;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Network;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\User as SupportUser;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class StaffModerationController extends LegacyController
{
    public function modtask(Request $request): Response|RedirectResponse
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        if (! Permission::can(PermissionEnum::MANAGE_USER_BASIC_INFO, User::findOrFail($currentUserId))) {
            Log::writeWithContext(
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
                'mod'
            );

            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        $action = (string) SupportContext::getPost('action');

        if ($action === 'confirmuser') {
            $userId = (int) SupportContext::getPost('userid');
            $confirm = (string) SupportContext::getPost('confirm');
            ModtaskRepository::confirmUser($userId, $confirm);

            return redirect(Http::protocolPrefix(Url::isSecure()).$baseUrl.'/unco.php?status=1');
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
            return $this->legacyAbortResponse('Error', "You have no permission to change user's class to ".UserClass::name((int) $class, false, false, true).'. BTW, how do you get here?');
        }

        $arr = ModtaskRepository::getUserArray($userId);
        if ($arr === null) {
            Log::writeWithContext(
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
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
                $msg = Locale::trans('user.msg_your_email_changed_from', [], $locale).$arr['email'].Locale::trans('user.msg_to_new', [], $locale).$email.Locale::trans('user.msg_by', [], $locale).$currentUser['username'];
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
                $msg = Locale::trans('user.msg_your_username_changed_from', [], $locale).$arr['username'].Locale::trans('user.msg_to_new', [], $locale).$username.Locale::trans('user.msg_by', [], $locale).$currentUser['username'];
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
                $msg = Locale::trans('user.msg_donor_status_changed_by', [], $locale).$currentUser['username'];
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
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
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
                $msg = Locale::trans('user.msg_your_warning_removed_by', [], $locale).$currentUser['username'].'.';
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
                $userModifyLogs[] = 'Warned by '.$currentUser['username'].".\nReason: {$warnPm}.";
                $msg = Locale::trans('user.msg_you_are_warned_by', [], $locale).$currentUser['username'].'.'.($warnPm ? Locale::trans('user.msg_reason', [], $locale).$warnPm : '');
                $updateset['warneduntil'] = null;
            } else {
                $warneduntil = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s')) + $warnLength * 604800);
                $dur = $warnLength.Locale::trans('user.msg_week', [], $locale).($warnLength > 1 ? Locale::trans('user.msg_s', [], $locale) : '');
                $msg = Locale::trans('user.msg_you_are_warned_for', [], $locale).$dur.Locale::trans('user.msg_by', [], $locale).$currentUser['username'].'.'.($warnPm ? Locale::trans('user.msg_reason', [], $locale).$warnPm : '');
                $userModifyLogs[] = "Warned for {$dur} by ".$currentUser['username'].".Reason: {$warnPm}";
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
            $updateset['timeswarned'] = new Expression('timeswarned + 1');
        }

        if (in_array($privacy, ['low', 'normal', 'strong'], true)) {
            $updateset['privacy'] = $privacy;
        }

        if (SupportContext::getPost('resetkey') !== null && SupportContext::getPost('resetkey') === 'yes') {
            $updateset['passkey'] = md5($arr['username'].date('Y-m-d H:i:s').$arr['passhash']);
        }

        if ($forumpost !== $curForumpost) {
            $locale = Locale::userLocale($userId);
            if ($forumpost === 'yes') {
                $userModifyLogs[] = "Posting enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_can_post', [], $locale);
            } else {
                $userModifyLogs[] = "Posting disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_two', [], $locale);
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
                $msg = Locale::trans('user.msg_your_upload_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_upload_can_upload', [], $locale);
            } else {
                $userModifyLogs[] = "Upload disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_upload_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_upload_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_two', [], $locale);
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
                $msg = Locale::trans('user.msg_your_download_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_can_download', [], $locale);
            } else {
                $userModifyLogs[] = "Download disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_download_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_download_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_three', [], $locale);
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
        $prefix = Http::protocolPrefix(Url::isSecure());

        return redirect($prefix.$baseUrl.'/'.($returnto !== '' ? $returnto : 'userdetails.php?id='.$userId));
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
                return $this->legacyAbortResponse('Delete Rule', 'You are about to delete a rule. Click <a class=altlink href=?act=del&id='.$id.'&sure=1>here</a> if you are sure.', false);
            }
            NexusDB::table('rules')->where('id', $id)->delete();
            NexusDB::cache_del('rules');

            return redirect('modrules.php');
        }

        if ($act === 'newsect') {
            $langs = Locale::languageList('rule_lang', null);
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
            $langs = Locale::languageList('site_lang', null);

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
}
