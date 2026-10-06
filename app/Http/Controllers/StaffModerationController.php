<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\RuleRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UsernameChangeType;
use App\Enums\UserPrivacy;
use App\Http\Requests\ModrulesRequest;
use App\Http\Requests\ModtaskRequest;
use App\Repositories\MessageRepository;
use App\Repositories\ModtaskRepository;
use App\Repositories\UserDetailRepository;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Input;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Network;
use App\Support\Security\PasskeyGenerator;
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

class StaffModerationController extends LegacyController
{
    public function __construct(private readonly RuleRepositoryInterface $ruleRepository, private readonly MessageRepository $messageRepository, private readonly UserDetailRepository $userDetailRepository,
        private readonly CurrentUser $currentUser,
        private readonly ModtaskRepository $modtaskRepository,
        private readonly PasskeyGenerator $passkeyGenerator,
    ) {}

    public function modtask(Request $request): Response|RedirectResponse
    {
        $deny = $this->modtaskPreamble();
        if ($deny !== null) {
            return $deny;
        }

        return $this->legacyAbortResponse('Error', 'Invalid action.');
    }

    public function modtaskPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/staff/modtask'.$suffix, 308);
    }

    public function modtaskSubmit(ModtaskRequest $request): Response|RedirectResponse
    {
        $deny = $this->modtaskPreamble();
        if ($deny !== null) {
            return $deny;
        }

        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost');

        $action = (string) request()->post('action');

        if ($action === 'confirmuser') {
            $userId = (int) request()->post('userid');
            $confirm = (string) request()->post('confirm');
            if (! in_array($confirm, ['pending', 'confirmed'], true)) {
                return $this->legacyAbortResponse('Error', 'Invalid confirmation status.');
            }
            $this->modtaskRepository->confirmUser($userId, $confirm);

            return redirect(Url::absolute($baseUrl).'/web/unco?status=1');
        }

        if ($action !== 'edituser') {
            return $this->legacyAbortResponse('Error', 'Invalid action.');
        }

        $userId = (int) request()->post('userid');
        $userInfo = $this->userDetailRepository->findOrFailById($userId);

        $class = $userInfo->class;

        $warned = (string) (request()->post('warned') ?? '');
        $warnLength = (int) (request()->post('warnlength') ?? 0);
        $warnPm = (string) (request()->post('warnpm') ?? '');
        $title = (string) (request()->post('title') ?? '');
        $avatar = (string) (request()->post('avatar') ?? '');
        $signature = (string) (request()->post('signature') ?? '');
        $uploadpos = request()->post('uploadpos') === 'yes';
        $downloadpos = request()->post('downloadpos') === 'yes';
        $privacy = (string) (request()->post('privacy') ?? 'normal');
        $forumpost = request()->post('forumpost') === 'yes';
        $supportlang = (string) (request()->post('supportlang') ?? '');
        $support = request()->post('support') === 'yes';
        $supportfor = (string) (request()->post('supportfor') ?? '');
        $moviepicker = request()->post('moviepicker') === 'yes';
        $pickfor = (string) (request()->post('pickfor') ?? '');
        $stafffor = (string) (request()->post('staffduties') ?? '');

        if (! Validators::isId($userId) || ! SupportUser::isValidUserClass($class)) {
            return $this->legacyAbortResponse('Error', 'Bad user ID or class ID.');
        }
        if (UserDisplay::currentClass() <= $class) {
            return $this->legacyAbortResponse('Error', "You have no permission to change user's class to ".UserClass::name((int) $class, false, false, true).'. BTW, how do you get here?');
        }

        $arr = $this->modtaskRepository->getUserArray($userId);
        if ($arr === null) {
            Log::writeWithContext(
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
                'mod'
            );

            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

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

        if (Permission::can(PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO, $this->userDetailRepository->findOrFailById($currentUserId))) {
            $locale = Locale::userLocale($userId);
            $email = (string) (request()->post('email') ?? '');
            $username = (string) (request()->post('username') ?? '');

            if ($arr['email'] !== $email) {
                $updateset['email'] = $email;
                $modifyLog = "Email changed from {$arr['email']} to {$email} by {$currentUser['username']}.";
                Log::writeWithContext($modifyLog, 'mod');
                $userModifyLogs[] = $modifyLog;
                $subject = Locale::trans('user.msg_email_change', [], $locale);
                $msg = Locale::trans('user.msg_your_email_changed_from', [], $locale).$arr['email'].Locale::trans('user.msg_to_new', [], $locale).$email.Locale::trans('user.msg_by', [], $locale).$currentUser['username'];
                $this->messageRepository->add([
                    'sender' => null,
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
                $this->messageRepository->add([
                    'sender' => null,
                    'receiver' => $userId,
                    'subject' => $subject,
                    'msg' => $msg,
                    'added' => now(),
                ]);
                $this->userDetailRepository->insertUsernameChangeLog([
                    'uid' => $arr['id'],
                    'operator' => $currentUser['username'],
                    'change_type' => UsernameChangeType::ADMIN->value,
                    'username_old' => $arr['username'],
                    'username_new' => $username,
                ]);
            }
        }

        $staffleaderClass = defined('UC_STAFFLEADER') ? \constant('UC_STAFFLEADER') : 0;
        if (UserDisplay::currentClass() == $staffleaderClass) {
            $locale = Locale::userLocale($userId);
            $donor = request()->post('donor') === 'yes';
            $donoruntil = request()->post('donoruntil') ?: null;
            $donated = (float) (request()->post('donated') ?? 0);
            $donatedCny = (float) (request()->post('donated_cny') ?? 0);
            $thisDonatedUsd = $donated - (float) $arr['donated'];
            $thisDonatedCny = $donatedCny - (float) $arr['donated_cny'];
            $memo = htmlspecialchars((string) request()->post('donation_memo'));

            if ($donated != (float) $arr['donated'] || $donatedCny != (float) $arr['donated_cny']) {
                $this->modtaskRepository->addFund($userId, $thisDonatedUsd, $thisDonatedCny, $memo);
                $updateset['donated'] = $donated;
                $updateset['donated_cny'] = $donatedCny;
            }

            $updateset['donor'] = $donor;
            $updateset['donoruntil'] = $donoruntil;

            $nowStr = date('Y-m-d H:i:s');
            if (($donor !== (bool) $arr['donor']) && (($donor && $donoruntil && $donoruntil >= $nowStr) || (! $donor))) {
                $subject = Locale::trans('user.msg_your_donor_status_changed', [], $locale);
                $msg = Locale::trans('user.msg_donor_status_changed_by', [], $locale).$currentUser['username'];
                $this->messageRepository->add([
                    'sender' => null,
                    'receiver' => $userId,
                    'subject' => $subject,
                    'msg' => $msg,
                    'added' => now(),
                ]);
                $userModifyLogs[] = "donor status changed by {$currentUser['username']}. Current donor status: ".($donor ? 'yes' : 'no');
            }
        }

        if ($curClass >= UserDisplay::currentClass()) {
            Log::writeWithContext(
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
                'mod'
            );

            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        if ($warned !== '' && (bool) $curWarned !== ($warned === 'yes')) {
            $updateset['warned'] = $warned === 'yes';
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

            $this->messageRepository->add([
                'sender' => null,
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
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);

            $updateset['warned'] = true;
            $updateset['lastwarned'] = now()->toDateTimeString();
            $updateset['warnedby'] = $currentUserId;
            $updateset['timeswarned'] = new Expression('timeswarned + 1');
        }

        if (in_array($privacy, ['low', 'normal', 'strong'], true)) {
            $updateset['privacy'] = UserPrivacy::fromStringSafe($privacy)->value;
        }

        if (request()->post('resetkey') !== null && request()->post('resetkey') === 'yes') {
            $updateset['passkey'] = $this->passkeyGenerator->generate();
        }

        if ($forumpost !== $curForumpost) {
            $locale = Locale::userLocale($userId);
            if ($forumpost) {
                $userModifyLogs[] = "Posting enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_can_post', [], $locale);
            } else {
                $userModifyLogs[] = "Posting disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_posting_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_posting_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_two', [], $locale);
            }
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        if ($uploadpos !== $curUploadpos) {
            $locale = Locale::userLocale($userId);
            if ($uploadpos) {
                $userModifyLogs[] = "Upload enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_upload_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_upload_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_upload_can_upload', [], $locale);
            } else {
                $userModifyLogs[] = "Upload disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_upload_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_upload_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_two', [], $locale);
            }
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        if ($downloadpos !== $curDownloadpos) {
            $locale = Locale::userLocale($userId);
            if ($downloadpos) {
                $userModifyLogs[] = "Download enabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_download_rights_restored', [], $locale);
                $msg = Locale::trans('user.msg_your_download_rights_restored', [], $locale).$currentUser['username'].Locale::trans('user.msg_you_can_download', [], $locale);
            } else {
                $userModifyLogs[] = "Download disabled by {$currentUser['username']}";
                $subject = Locale::trans('user.msg_download_rights_removed', [], $locale);
                $msg = Locale::trans('user.msg_your_download_rights_removed', [], $locale).$currentUser['username'].Locale::trans('user.msg_probably_reason_three', [], $locale);
            }
            $this->messageRepository->add([
                'sender' => null,
                'receiver' => $userId,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }

        $modcomment = trim((string) (request()->post('modcomment') ?? ''));
        if ($modcomment !== '') {
            $userModifyLogs[] = date('Y-m-d').' - '.$modcomment.' (by '.$currentUser['username'].')';
        }

        $this->modtaskRepository->updateUser($userId, $updateset);

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
            $this->userDetailRepository->insertUserModifyLogs($insert);
        }

        Cache::clearUser($userId, $arr['passhash']);

        $returnto = (string) request()->post('returnto');
        $prefix = Url::absolute($baseUrl);

        return redirect($prefix.'/'.($returnto !== '' ? $returnto : '/userdetails?id='.$userId));
    }

    public function modrules(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the Rules, sorry.');
        }

        $act = (string) (request()->query('act') ?? 'list');

        if ($act === 'del') {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        if ($act === 'newsect') {
            $langs = Locale::languageList('rule_lang', null);
            $defLang = SiteConfig::current()->main->defaultLang();

            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'newsect',
                'langs' => $langs,
                'deflang' => $defLang,
            ]);
        }

        if ($act === 'edit') {
            $id = (int) (request()->query('id') ?? 0);
            $rule = $this->ruleRepository->findById($id) ?? [];
            $langs = Locale::languageList('site_lang', null);

            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'edit',
                'rule' => $rule,
                'langs' => $langs,
            ]);
        }

        $rules = array_map(function (array $arr): array {
            $arr['textHtml'] = Format::formatComment($arr['text']);

            return $arr;
        }, $this->ruleRepository->listAllWithLang());

        return $this->legacyPage($request, 'modrules', true, [
            'mode' => 'list',
            'rows' => $rules,
        ]);

    }

    private function modtaskPreamble(): ?Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);

        if (! Permission::can(PermissionEnum::MANAGE_USER_BASIC_INFO, $this->userDetailRepository->findOrFailById($currentUserId))) {
            Log::writeWithContext(
                'User '.($currentUser['username'] ?? '')." (id: {$currentUserId}) is hacking user's profile. IP : ".Network::clientIp(),
                'mod'
            );

            return $this->legacyAbortResponse('Error', 'Permission denied. For security reason, we logged this action');
        }

        return null;
    }

    public function modrulesPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/staff/modrules'.$suffix, 308);
    }

    public function modrulesSubmit(ModrulesRequest $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the Rules, sorry.');
        }

        $act = (string) (request()->query('act') ?? 'list');

        if ($act === 'addsect') {
            $title = (string) request()->post('title');
            $text = (string) request()->post('text');
            $language = (int) request()->post('language');
            $this->ruleRepository->insert([
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            Cache::forgetWithLocales('rules');

            return redirect('/web/modrules');
        }

        if ($act === 'edited') {
            $id = (int) (request()->post('id') ?? 0);
            $title = (string) request()->post('title');
            $text = (string) request()->post('text');
            $language = (int) request()->post('language');
            $this->ruleRepository->updateById($id, [
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            Cache::forgetWithLocales('rules');

            return redirect('/web/modrules');
        }

        if ($act === 'del') {
            $id = (int) (request()->post('id') ?? 0);
            $sure = (int) (request()->post('sure') ?? 0);
            if (! $sure) {
                return $this->legacyAbortResponse('Delete Rule', 'You are about to delete a rule. Click <a class=altlink href="?act=edit&id='.$id.'">here</a> to go back. To confirm deletion, use the delete button on the rules page.', false);
            }
            $this->ruleRepository->deleteById($id);
            Cache::forgetWithLocales('rules');

            return redirect('/web/modrules');
        }

        return $this->modrules($request);
    }
}
