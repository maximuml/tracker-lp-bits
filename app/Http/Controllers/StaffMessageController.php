<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BulkUserMessageJob;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Http\SafeReturnUrl;
use App\Support\Input;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffMessageController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
    ) {}

    public function staffmess(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $currentUser = $this->currentUser->get() ?? [];
        $classes = array_chunk(User::$classes, 4, true);
        $returntoQuery = $request->query('returnto');
        $httpReferer = Input::serverValue('HTTP_REFERER');

        return $this->legacyPage($request, 'staffmess', true, [
            'stdheadMsgalert' => false,
            'classes' => $classes,
            'body' => htmlspecialchars((string) request()->query('body')),
            'receiver' => (int) (request()->query('receiver') ?? 0),
            'username' => htmlspecialchars((string) ($currentUser['username'] ?? '')),
            'sent' => (int) (request()->query('sent') ?? 0),
            'showReturnto' => (bool) ($returntoQuery || $httpReferer),
            'returnto' => htmlspecialchars((string) ($returntoQuery ?? $httpReferer)),
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

        $currentUser = $this->currentUser->get() ?? [];
        $senderId = request()->post('sender') === 'system' ? null : (int) ($currentUser['id'] ?? 0);
        $subject = trim((string) request()->post('subject'));
        $msg = trim((string) request()->post('msg'));

        if ($msg === '') {
            return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
        }

        $selectedClasses = (array) request()->post('classes');
        if (empty($selectedClasses)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }
        foreach ($selectedClasses as $class) {
            $classId = (int) $class;
            if (! Validators::isId($classId) && $classId !== 0) {
                return $this->legacyAbortResponse('Error', 'Invalid Class');
            }
        }

        $classIds = array_map('intval', $selectedClasses);

        $dryRun = (bool) $request->post('dry_run', false);
        $idempotencyKey = (string) $request->post('idempotency_key', Str::uuid()->toString());

        BulkUserMessageJob::dispatch(
            classIds: $classIds,
            senderId: $senderId,
            subject: $subject,
            body: $msg,
            actorId: $senderId,
            idempotencyKey: $idempotencyKey,
            dryRun: $dryRun,
        );

        return redirect('staffmess.php?sent=1');
    }

    public function contactstaff(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'contactstaff', true, [
        ]);

    }

    public function takecontact(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse(__('legacy/takecontact.std_error'), __('legacy/takecontact.std_method'));
        }

        $msg = trim((string) request()->post('body'));
        $subject = trim((string) request()->post('subject'));

        if ($msg === '') {
            return $this->legacyAbortResponse(__('legacy/takecontact.std_error'), __('legacy/takecontact.std_please_enter_something'));
        }
        if ($subject === '') {
            return $this->legacyAbortResponse(__('legacy/takecontact.std_error'), __('legacy/takecontact.std_please_define_subject'));
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $timeNow = (int) $this->globals->get('TIMENOW', time());

        if (UserDisplay::currentClass() < $moderatorClass) {
            $last = $curUser['last_staffmsg'] ?? null;
            if ($last !== null && strtotime((string) $last) > ($timeNow - 60)) {
                $secs = 60 - ($timeNow - strtotime((string) $last));

                return $this->legacyAbortResponse(
                    __('legacy/takecontact.std_error'),
                    (__('legacy/takecontact.std_message_flooding')).$secs.(__('legacy/takecontact.std_second')).($secs == 1 ? '' : (__('legacy/takecontact.std_s'))).(__('legacy/takecontact.std_before_sending_pm'))
                );
            }
        }

        StaffMessage::add($currentUserId, $subject, $msg);

        User::query()->where('id', $currentUserId)->update(['last_staffmsg' => date('Y-m-d H:i:s')]);
        Cache::clearStaffMessage();

        $returnto = (string) request()->post('returnto');
        if ($returnto !== '') {
            return redirect(SafeReturnUrl::filter($returnto));
        }

        return $this->legacyPage($request, 'takecontact', true, [
        ]);
    }
}
