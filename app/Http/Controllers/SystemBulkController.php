<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Email;
use App\Support\Environment;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyAuth;
use App\Support\Locale;
use App\Support\Lock;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Mail;
use App\Support\Permissions;
use App\Support\SetlistLookup;
use App\Support\Url;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemBulkController extends LegacyController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function takeamountupload(Request $request): Response|RedirectResponse|View
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Sorry', 'Permission denied.');
        }

        if ($request->isMethod('get')) {
            if (request()->query('sent') == '1') {
                Html::stdhead('Add Upload');
                Html::stdMessage('Success', 'Upload amount has been added successfully.');
                Html::stdfoot();

                return response('');
            }

            return $this->legacyAbortResponse('Error', 'Permission denied!');
        }

        $curUser = app(CurrentUser::class)->get() ?? [];
        $senderId = request()->post('sender') === 'system' ? null : (int) ($curUser['id'] ?? 0);
        $added = date('Y-m-d H:i:s');
        $msg = trim((string) request()->post('msg'));
        $amount = request()->post('amount');

        if ($msg === '' || $amount === null || $amount === '') {
            return $this->legacyAbortResponse('Error', 'Don\'t leave any fields blank.');
        }
        if (! is_numeric($amount)) {
            return $this->legacyAbortResponse('Error', 'amount must be numeric');
        }

        $classSet = (array) request()->post('clases');
        foreach ($classSet as $class) {
            if (! Validators::isId($class) && $class != 0) {
                return $this->legacyAbortResponse('Error', 'Invalid Class');
            }
        }

        $subject = trim((string) request()->post('subject'));
        $bytes = Format::bytesFromUnit($amount, 'G');

        User::query()->whereIn('class', $classSet)->increment('uploaded', $bytes);

        $userIds = User::query()->whereIn('class', $classSet)->pluck('id')->all();
        foreach ($userIds as $userId) {
            DB::table('messages')->insert([
                'sender' => $senderId,
                'receiver' => (int) $userId,
                'added' => $added,
                'subject' => $subject,
                'msg' => $msg,
            ]);
        }

        return redirect('takeamountupload.php?sent=1');

    }

    public function takeinvite(Request $request): Response|RedirectResponse
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/takeinvite.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $lockName = sprintf('takeinvite:%s', $currentUserId);
        $lock = new Lock($lockName, 10);
        if (! $lock->get()) {
            $errMsg = Locale::trans('nexus.do_not_repeat', [], null);

            return $this->legacyAbortResponse($errMsg, $errMsg);
        }

        try {
            LegacyAuth::registrationCheckFromContext('invitesystem', true, false);

            $userRep = $this->userRepository;
            try {
                $sendText = $userRep->getInviteBtnText($currentUserId);
            } catch (\Exception $exception) {
                $lang = (array) app(Globals::class)->get('lang_takeinvite', []);

                return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $exception->getMessage());
            }

            $email = Input::unescape(htmlspecialchars(trim((string) request()->post('email'))));
            $email = Email::sanitizeForDisplay($email);
            $preRegisterUsername = (string) request()->post('pre_register_username');
            $isPreRegisterEmailAndUsername = SiteConfig::current()->system->isInvitePreEmailAndUsername();
            $lang = (array) app(Globals::class)->get('lang_takeinvite', []);

            if (strlen($preRegisterUsername) > 12) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_username_too_long'] ?? 'Username too long.');
            }
            if (! $email) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_enter_email'] ?? 'Enter an email.');
            }
            if (! Email::isWellFormed($email)) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_invalid_email_address'] ?? 'Invalid email.');
            }

            $body = str_replace('<br />', '<br />', nl2br(trim(strip_tags((string) request()->post('body')))));
            if (! $body) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_enter_personal_message'] ?? 'Enter a message.');
            }

            if ($isPreRegisterEmailAndUsername) {
                if (empty($preRegisterUsername)) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        Locale::trans('invite.require_pre_register_username', [], null)
                    );
                }
                if (! Validators::isUsername($preRegisterUsername)) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        Locale::trans('user.username_invalid', ['username' => $preRegisterUsername], null)
                    );
                }
                if (User::query()->where('username', $preRegisterUsername)->exists()) {
                    return $this->legacyAbortResponse(
                        $lang['head_invitation_failed'] ?? 'Error',
                        Locale::trans('user.username_already_exists', ['username' => $preRegisterUsername], null)
                    );
                }
            }

            if (User::query()->where('email', $email)->count() > 0) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_email_address'].htmlspecialchars($email).$lang['std_is_in_use']);
            }
            if (Invite::query()->where('invitee', $email)->count() > 0) {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_invitation_already_sent_to'].htmlspecialchars($email).$lang['std_await_user_registeration']);
            }

            $hashPost = (string) request()->post('hash');
            if ($hashPost === '') {
                return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['std_must_select_invite'] ?? 'Select an invite.');
            }

            $hashRecord = null;
            $timeNow = (int) app(Globals::class)->get('TIMENOW', time());
            if ($hashPost === 'permanent') {
                $inviter = User::query()->findOrFail($currentUserId);
                $hash = md5(mt_rand(1, 10000).$inviter->username.$timeNow.$inviter->passhash);
            } else {
                $hashRecord = Invite::query()->where('inviter', $currentUserId)->where('hash', $hashPost)->first();
                if (! $hashRecord instanceof Invite) {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['hash_not_exists'] ?? 'Hash does not exist.');
                }
                if ($hashRecord->invitee !== '') {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', 'hash '.$lang['std_is_in_use']);
                }
                if ($hashRecord->expired_at !== null && $hashRecord->expired_at->lt(now())) {
                    return $this->legacyAbortResponse($lang['head_invitation_failed'] ?? 'Error', $lang['hash_expired'] ?? 'Hash expired.');
                }
                $hash = $hashPost;
            }

            $siteName = Setting::getSiteName();
            $title = $siteName.$lang['mail_tilte'];
            $signupUrl = Url::schemeAndHost(Url::isSecure())."/signup.php?type=invite&invitenumber=$hash";
            $mailTwo = sprintf($lang['mail_two'], $siteName, $siteName);
            $mailFour = sprintf($lang['mail_four'], $siteName);
            $reportMail = (string) app(Globals::class)->get('REPORTMAIL', '');
            $mailSix = sprintf($lang['mail_six'], $reportMail, $siteName);
            $inviteTimeout = (string) app(Globals::class)->get('invite_timeout', '');

            $message = $lang['mail_one'].$curUser['username'].$mailTwo.PHP_EOL
                .'<b><a href="javascript:void(null)" onclick="window.open('.$signupUrl.')">'.$lang['mail_here'].'</a></b><br />'.PHP_EOL
                .$signupUrl.PHP_EOL
                .'<br />'.$lang['mail_three'].$inviteTimeout.$mailFour.$curUser['username'].$lang['mail_five'].'<br />'.PHP_EOL
                .$body.PHP_EOL
                .'<br /><br />'.$mailSix;

            $sendResult = Mail::sentLegacy(
                $email,
                $siteName,
                (string) app(Globals::class)->get('SITEEMAIL', ''),
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

                if ($hashRecord instanceof Invite) {
                    $hashRecord->update($update);
                } else {
                    $insert = [
                        'inviter' => $currentUserId,
                        'invitee' => $email,
                        'hash' => $hash,
                        'time_invited' => now()->toDateTimeString(),
                    ] + $update;
                    unset($insert['valid']); // already included
                    Invite::query()->insert($insert);
                    User::query()->where('id', $currentUserId)->decrement('invites');
                }
            }
        } finally {
            $lock->release();
        }

        return redirect('/invite.php?id='.$currentUserId.'&sent=1');
    }

    public function takeupdate(Request $request): Response|RedirectResponse
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/takeupdate.php'.($qs ? '?'.$qs : ''));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $delreport = (array) request()->post('delreport');
        if (empty($delreport)) {
            $langFunctions = (array) app(Globals::class)->get('lang_functions', []);

            return $this->legacyAbortResponse('Error', $langFunctions['select_at_least_one_record'] ?? 'Select at least one record.');
        }

        $delreportIds = array_map('intval', array_filter($delreport, 'is_numeric'));
        if (empty($delreportIds)) {
            return $this->legacyAbortResponse('Error', 'Invalid report ids.');
        }

        $cache = app(LegacyRedisCache::class);
        if (request()->post('setdealt')) {
            DB::table('reports')
                ->whereIn('id', $delreportIds)
                ->where('dealtwith', 0)
                ->update(['dealtwith' => 1, 'dealtby' => $currentUserId]);
            $cache?->delete_value('staff_new_report_count', true);
        } elseif (request()->post('delete')) {
            DB::table('reports')->whereIn('id', $delreportIds)->delete();
            $cache?->delete_value('staff_new_report_count', true);
            $cache?->delete_value('staff_report_count', true);
        }

        return redirect('/reports.php');
    }

    public function incrementBulk(Request $request): View|RedirectResponse|Response
    {

        return $this->legacyPage($request, 'increment-bulk', true);

    }

    public function setlistLookup(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $url = trim((string) $request->input('url', ''));

        if ($name === '' && $url === '') {
            return response()->json(['success' => false, 'error' => 'Torrent name or setlist URL is required.']);
        }

        try {
            if ($url !== '') {
                $host = parse_url($url, PHP_URL_HOST) ?: '';
                if (! in_array(strtolower($host), ['www.setlist.fm', 'setlist.fm'], true)) {
                    return response()->json(['success' => false, 'error' => 'Only setlist.fm URLs are allowed.']);
                }
                $result = SetlistLookup::fromUrl($url);
            } else {
                $result = SetlistLookup::fromTorrentName($name);
            }

            return response()->json($result);
        } catch (\Throwable $e) {
            Logger::writeWithContext((string) ($e->getMessage()."\n".$e->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(['success' => false, 'error' => 'Setlist lookup failed.']);
        }
    }

    public function takeIncrementBulk(Request $request): Response|RedirectResponse
    {
        if (! $request->isMethod('POST')) {
            return $this->legacyAbortResponse('Error', 'Permission denied!');
        }

        if ((int) UserDisplay::currentClass() < UserClassEnum::SYSOP->value) {
            return $this->legacyAbortResponse('Sorry', 'Permission denied.');
        }

        $lang = (array) (app(Globals::class)->get('lang_incrementbulk') ?? []);
        $validTypeMap = (array) ($lang['types'] ?? []);

        $currentUser = app(CurrentUser::class)->get() ?? [];
        $senderId = $request->input('sender') === 'system' ? null : ((int) ($currentUser['id'] ?? 0));
        $added = date('Y-m-d H:i:s');
        $msg = trim((string) $request->input('msg', ''));
        $amount = $request->input('amount');
        $type = (string) $request->input('type', '');

        if ($msg === '' || $amount === '' || $amount === null || $type === '') {
            return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
        }

        if (! is_numeric($amount)) {
            return $this->legacyAbortResponse('Error', 'amount must be numeric');
        }

        if (! isset($validTypeMap[$type])) {
            return $this->legacyAbortResponse('Error', 'Invalid type');
        }

        if ($type === 'uploaded') {
            $amount = (int) Format::bytesFromUnit($amount, 'G');
        } else {
            $amount = (int) $amount;
        }

        $isTypeTmpInvite = $type === 'tmp_invites';
        $subject = trim((string) $request->input('subject', ''));
        $duration = 0;
        $size = 2000;
        $page = 1;

        $classIds = [];
        $classes = $request->input('classes', []);
        if (is_array($classes) && ! empty($classes)) {
            $classIds = array_filter(array_map('intval', $classes), fn ($v) => $v > 0);
        }

        if (empty($classIds)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }

        if ($isTypeTmpInvite) {
            $duration = (int) $request->input('duration', 0);
            if ($duration <= 0) {
                return $this->legacyAbortResponse('Sorry', 'Invalid duration: '.$duration);
            }
        }

        set_time_limit(300);

        while (true) {
            $msgRows = [];
            $idArr = [];
            $offset = ($page - 1) * $size;

            $users = DB::table('users')
                ->whereIn('class', $classIds)
                ->where('enabled', 'yes')
                ->where('status', 'confirmed')
                ->offset($offset)
                ->limit($size)
                ->get(['id']);

            foreach ($users as $userRow) {
                $id = (int) $userRow->id;
                $idArr[] = $id;
                $msgRows[] = [
                    'sender' => $senderId,
                    'receiver' => $id,
                    'added' => $added,
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }

            if (empty($idArr)) {
                break;
            }

            $idStr = implode(',', $idArr);
            $idRedisKey = sprintf('temporary_invite:%s', microtime(true));
            Cache::put($idRedisKey, $idStr);

            if ($isTypeTmpInvite) {
                $command = sprintf('invite:tmp %s %s %s', $idRedisKey, $duration, $amount);
                $output = Environment::run($command, 'string', true, true);
                $outputStr = is_array($output) ? implode("\n", $output) : (string) $output;
                Log::writeWithContext((string) sprintf('command: %s, output: %s', $command, $outputStr), 'info');
            } else {
                DB::table('users')->whereIn('id', $idArr)->increment($type, $amount);
            }

            if (! empty($msgRows)) {
                DB::table('messages')->insert($msgRows);
            }

            $page++;
        }

        return redirect('/increment-bulk.php?sent=1&type='.$type);
    }
}
