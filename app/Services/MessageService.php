<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserAcceptPms;
use App\Models\Message;
use App\Models\User;
use App\Policies\MessagePolicy;
use App\Repositories\MessageLookupRepository;
use App\Repositories\MessageRepository;
use App\Repositories\UserAccountRepository;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Http\SafeReturnUrl;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\Url;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles message action mutations (takeMessage, deletemessage) and
 * dispatches mailbox mutations (moveordel, editmailboxes2, deletemessage)
 * to {@see MessageMailboxService}. Page rendering is handled by
 * MessagePageService.
 */
class MessageService
{
    public function __construct(
        private readonly MessagePolicy $policy,
        private readonly MessageMailboxService $mailbox,
        private readonly MessageLookupRepository $messageLookupRepository,
        private readonly UserAccountRepository $userAccountRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function takeMessage(Request $request): RedirectResponse
    {
        if (! $request->isMethod('POST')) {
            LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_permission_denied'));
        }

        $sender = Auth::user();
        if (! $sender instanceof User) {
            LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_permission_denied'));
        }

        $origmsg = (int) $request->input('origmsg', 0);
        $body = trim((string) $request->input('body', ''));
        $isForward = $request->input('forward') === '1';
        $save = $request->input('save') === 'yes';
        $returnto = (string) $request->input('returnto', '');
        $subject = trim((string) $request->input('subject', ''));
        $delete = $request->input('delete') === 'yes';

        if ($isForward) {
            if ($origmsg <= 0) {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_invalid_id'));
            }

            $origmsgRecord = $this->messageLookupRepository->findVisibleToUser($origmsg, (int) $sender->id);

            if (! $origmsgRecord) {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_no_permission_forwarding'));
            }

            $to = trim((string) $request->input('to', ''));
            if ($to === '') {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_must_enter_username'));
            }

            $receiver = UserDisplay::userIdFromName($to);
            if ($receiver <= 0) {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_user_not_exist'));
            }

            $locale = Locale::userLocale($receiver);
            $origSenderName = (int) $origmsgRecord->sender === 0
                ? Locale::trans('message.msg_system', [], $locale)
                : '[url=/userdetails?id='.$origmsgRecord->sender.']'.UserDisplay::plainUsername($origmsgRecord->sender).'[/url]';

            $body = '-------- '.Locale::trans('message.msg_original_message_from', [], $locale).$origSenderName." --------\n"
                .$origmsgRecord->msg."\n\n"
                .($body ? '-------- [url=/userdetails?id='.$sender->id.']'.$sender->username.'[/url][i] Wrote at '.date('Y-m-d H:i:s').":[/i] --------\n".$body : '');
        } else {
            $receiver = (int) $request->input('receiver', 0);
            if ($receiver <= 0 || ($origmsg > 0 && ! Validators::isId($origmsg))) {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_invalid_id'));
            }

            if ($body === '') {
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_please_enter_something'));
            }
        }

        if (! Permission::can(PermissionEnum::STAFF_MEMBER, $sender)) {
            $lastPmTs = $sender->last_pm?->getTimestamp();
            if ($lastPmTs !== null && $lastPmTs > (time() - 10)) {
                $secs = 60 - (time() - $lastPmTs);
                LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_message_flooding_denied').$secs.__('legacy/takemessage.std_before_sending_pm'));
            }
        }

        $recipient = $this->userAccountRepository->findByIdFields($receiver, ['*']);
        if (! $recipient) {
            LegacyResponse::abort(__('legacy/takemessage.std_error'), __('legacy/takemessage.std_user_not_exist'));
        }

        // W1-03: Use MessagePolicy for authorization checks
        if (! $this->policy->sendTo($sender, $recipient)) {
            if ($recipient->parked) {
                LegacyResponse::abort(__('legacy/takemessage.std_refused'), __('legacy/takemessage.std_account_parked'));
            }

            if ($recipient->acceptpms === UserAcceptPms::YES) {
                LegacyResponse::abort(__('legacy/takemessage.std_refused'), __('legacy/takemessage.std_user_blocks_your_pms'));
            } elseif ($recipient->acceptpms === UserAcceptPms::FRIENDS) {
                LegacyResponse::abort(__('legacy/takemessage.std_refused'), __('legacy/takemessage.std_user_accepts_friends_pms'));
            } elseif ($recipient->acceptpms === UserAcceptPms::NO) {
                LegacyResponse::abort(__('legacy/takemessage.std_refused'), __('legacy/takemessage.std_user_blocks_all_pms'));
            }

            LegacyResponse::abort(__('legacy/takemessage.std_refused'), __('legacy/takemessage.std_permission_denied'));
        }

        $message = $this->messageRepository->add([
            'sender' => $sender->id,
            'receiver' => $recipient->id,
            'msg' => $body,
            'subject' => $subject,
            'added' => now(),
            'saved' => $save,
            'location' => 1,
        ]);

        $sender->update(['last_pm' => date('Y-m-d H:i:s')]);
        Cache::clearUser($sender->id, $sender->passkey ?? '');
        Cache::forgetWithLocales('user_'.$sender->id.'_outbox_count');

        $siteConfig = SiteConfig::current();
        if ($siteConfig->smtp->emailNotify() && $siteConfig->smtp->type() !== 'none') {
            $notifs = (string) $recipient->notifs;
            if (str_contains($notifs, '[pm]')) {
                $this->sendPmNotification($recipient, $sender, $subject, $message->id);
            }
        }

        if ($origmsg > 0 && $delete) {
            $orig = $this->messageLookupRepository->findById($origmsg);
            if ($orig && $orig->receiver == $sender->id) {
                if ($orig->saved === 'no') {
                    $orig->delete();
                } else {
                    $orig->update(['location' => '0']);
                }
            }
        }

        $redirect = SafeReturnUrl::filter($returnto, '/web/messages');

        return redirect($redirect);
    }

    public function deletemessage(Request $request): RedirectResponse
    {
        $sender = Auth::user();
        if (! $sender instanceof User) {
            LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_bad_message_id'));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_bad_message_id'));
        }

        $type = (string) $request->input('type', '');

        if ($type === 'in') {
            $msg = $this->messageLookupRepository->findByIdFields($id, ['id', 'receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || ! $this->policy->deleteInbox($sender, $msg)) {
                LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_not_suggested'));
            }

            if ((int) $msg->location === 0) {
                LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_not_in_inbox'));
            }

            if ($msg->saved === 'yes') {
                $msg->update(['location' => '0', 'unread' => false]);
            } else {
                $msg->delete();
            }

            Cache::clearInboxCount($sender->id);
        } elseif ($type === 'out') {
            $msg = $this->messageLookupRepository->findByIdFields($id, ['id', 'receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || ! $this->policy->deleteSentbox($sender, $msg)) {
                LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_not_suggested'));
            }

            if ((int) $msg->location === 0 && $msg->saved === 'no') {
                LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_not_in_sentbox'));
            }

            if ((int) $msg->location === 0) {
                $msg->delete();
            } else {
                $msg->update(['saved' => false]);
            }

            Cache::forgetWithLocales('user_'.$sender->id.'_outbox_count');
        } else {
            LegacyResponse::abort(__('legacy/functions.std_error'), __('legacy/deletemessage.std_unknown_pm_type'));
        }

        $redirect = $type === 'out' ? '/web/messages?out=1' : '/web/messages';

        return redirect($redirect);
    }

    private function sendPmNotification(User $recipient, User $sender, string $subject, int $messageId): void
    {
        $locale = Locale::userLocale($recipient->id);
        $siteConfig = SiteConfig::current();
        $siteName = $siteConfig->basic->siteName();
        $siteEmail = $siteConfig->main->siteEmail();

        $baseUrl = Url::absolute($siteConfig->basic->baseUrl());
        if ($baseUrl === '') {
            $baseUrl = Url::schemeAndHost();
        }
        $messageUrl = $baseUrl.'/web/messages?action=viewmessage&id='.$messageId;

        $title = $siteName.' '.Locale::trans('message.mail_received_pm_from', [], $locale).$sender->username.'!';
        $body = view('emails.new-pm', [
            'recipientUsername' => (string) $recipient->username,
            'senderUsername' => (string) $sender->username,
            'subject' => $subject,
            'date' => date('Y-m-d H:i:s'),
            'messageUrl' => $messageUrl,
            'siteName' => $siteName,
            'locale' => $locale,
        ])->render();

        Mail::queueLegacy(
            (string) $recipient->email,
            $siteName,
            $siteEmail,
            $title,
            (string) nl2br($body),
            'sendmessage',
            false,
            false,
            '',
            'UTF-8'
        );
    }

    public function handleMessagesActionPublic(Request $request): ?RedirectResponse
    {
        return $this->handleMessagesAction($request);
    }

    public function moveOrDelete(Request $request): RedirectResponse
    {
        return $this->mailbox->handleMoveOrDel($request);
    }

    public function editMailboxes(Request $request): RedirectResponse
    {
        return $this->mailbox->handleEditMailboxes($request);
    }

    public function deleteMailboxMessage(Request $request): RedirectResponse
    {
        return $this->mailbox->handleDeleteMessage($request);
    }

    private function handleMessagesAction(Request $request): ?RedirectResponse
    {
        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = (string) $request->input('action', 'viewmailbox');
        }

        if ($action === 'viewmessage') {
            $id = (int) $request->input('id', 0);
            $user = Auth::user();
            if ($id <= 0 || ! $user instanceof User) {
                return redirect('/web/messages');
            }

            $message = $this->messageLookupRepository->findById($id);
            if (! $message || ! $this->policy->view($user, $message)) {
                return redirect('/web/messages');
            }

            return null;
        }

        if ($action === 'moveordel') {
            if (! $request->isMethod('post')) {
                return redirect('/web/messages');
            }

            return $this->mailbox->handleMoveOrDel($request);
        }

        if ($action === 'editmailboxes2') {
            if (! $request->isMethod('post')) {
                return redirect('/web/messages');
            }

            return $this->mailbox->handleEditMailboxes($request);
        }

        if ($action === 'deletemessage') {
            if (! $request->isMethod('post')) {
                return redirect('/web/messages');
            }

            return $this->mailbox->handleDeleteMessage($request);
        }

        return null;
    }
}
