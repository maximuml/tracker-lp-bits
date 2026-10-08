<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Requests\ContactstaffSubmitRequest;
use App\Http\Requests\SendContactStaffRequest;
use App\Http\Requests\SendStaffMessageRequest;
use App\Http\Requests\StaffmessSubmitRequest;
use App\Jobs\BulkUserMessageJob;
use App\Models\User;
use App\Repositories\StaffMessageRepository;
use App\Support\Cache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Http\SafeReturnUrl;
use App\Support\RequestValues;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffMessageController extends BasePageController
{
    public function __construct(private readonly StaffMessageRepository $staffMessageRepository, private readonly UserRepositoryInterface $userRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    public function staffmess(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Sorry', 'Access denied.');
        }

        $currentUser = $this->currentUser->get() ?? [];
        $classes = array_chunk(User::$classes, 4, true);
        $returntoQuery = $request->query('returnto');
        $httpReferer = RequestValues::serverValue('HTTP_REFERER');

        return $this->renderPage($request, 'staffmess', true, [
            'stdheadMsgalert' => false,
            'classes' => $classes,
            'body' => SafeHtml::fromTrustedHtml(htmlspecialchars((string) request()->query('body'))),
            'receiver' => (int) (request()->query('receiver') ?? 0),
            'username' => SafeHtml::fromTrustedHtml(htmlspecialchars((string) ($this->currentUser->username()))),
            'sent' => (int) (request()->query('sent') ?? 0),
            'showReturnto' => (bool) ($returntoQuery || $httpReferer),
            'returnto' => htmlspecialchars((string) ($returntoQuery ?? $httpReferer)),
        ]);
    }

    public function staffmessSubmit(StaffmessSubmitRequest $request): View|RedirectResponse|Response
    {
        return $this->staffmess($request);
    }

    public function sendStaffMessage(SendStaffMessageRequest $request): Response|RedirectResponse
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        $currentUser = $this->currentUser->get() ?? [];
        $senderId = request()->post('sender') === 'system' ? null : (int) ($this->currentUser->id());
        $subject = trim((string) request()->post('subject'));
        $msg = trim((string) request()->post('msg'));

        if ($msg === '') {
            return $this->abortResponse('Error', "Don't leave any fields blank.");
        }

        $selectedClasses = (array) request()->post('classes');
        if (empty($selectedClasses)) {
            return $this->abortResponse('Error', 'No valid filter');
        }
        foreach ($selectedClasses as $class) {
            $classId = (int) $class;
            if (! Validators::isId($classId) && $classId !== 0) {
                return $this->abortResponse('Error', 'Invalid Class');
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

        return redirect('/web/staffmess?sent=1');
    }

    public function contactstaff(Request $request): View|RedirectResponse|Response
    {
        return $this->renderPage($request, 'contactstaff', true, [
        ]);

    }

    public function contactstaffSubmit(ContactstaffSubmitRequest $request): View|RedirectResponse|Response
    {
        return $this->contactstaff($request);
    }

    public function sendContactStaff(SendContactStaffRequest $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];

        $msg = trim((string) request()->post('body'));
        $subject = trim((string) request()->post('subject'));

        if ($msg === '') {
            return $this->abortResponse(__('takecontact.std_error'), __('takecontact.std_please_enter_something'));
        }
        if ($subject === '') {
            return $this->abortResponse(__('takecontact.std_error'), __('takecontact.std_please_define_subject'));
        }

        $currentUserId = (int) ($this->currentUser->id());
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $timeNow = defined('TIMENOW') ? (int) constant('TIMENOW') : time();

        if (UserDisplay::currentClass() < $moderatorClass) {
            $last = $this->currentUser->value('last_staffmsg', null);
            if ($last !== null && strtotime((string) $last) > ($timeNow - 60)) {
                $secs = 60 - ($timeNow - strtotime((string) $last));

                return $this->abortResponse(
                    __('takecontact.std_error'),
                    (__('takecontact.std_message_flooding')).$secs.(__('takecontact.std_second')).($secs == 1 ? '' : (__('takecontact.std_s'))).(__('takecontact.std_before_sending_pm'))
                );
            }
        }

        $this->staffMessageRepository->add($currentUserId, $subject, $msg);

        $this->userRepository->updateFields($currentUserId, ['last_staffmsg' => date('Y-m-d H:i:s')]);
        Cache::clearStaffMessage();

        $returnto = (string) request()->post('returnto');
        if ($returnto !== '') {
            return redirect(SafeReturnUrl::filter($returnto));
        }

        return $this->renderPage($request, 'takecontact', true, [
        ]);
    }
}
