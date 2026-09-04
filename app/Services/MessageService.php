<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Http\SafeReturnUrl;
use App\Support\Language;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\Url;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Handles message action mutations (takeMessage, deletemessage, moveordel,
 * editmailboxes2). Page rendering is handled by MessagePageService.
 */
class MessageService
{
    public function takeMessage(Request $request): RedirectResponse
    {
        if (! $request->isMethod('POST')) {
            $lang = $this->lang('takemessage');
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_permission_denied'] ?? 'Permission denied.');
        }

        $sender = Auth::user();
        if (! $sender instanceof User) {
            $lang = $this->lang('takemessage');
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_permission_denied'] ?? 'Permission denied.');
        }
        if (! $sender instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }

        $lang = $this->lang('takemessage');

        $origmsg = (int) $request->input('origmsg', 0);
        $body = trim((string) $request->input('body', ''));
        $isForward = $request->input('forward') === '1';
        $save = $request->input('save') === 'yes';
        $returnto = (string) $request->input('returnto', '');
        $subject = trim((string) $request->input('subject', ''));
        $delete = $request->input('delete') === 'yes';

        if ($isForward) {
            if ($origmsg <= 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_invalid_id'] ?? 'Invalid ID.');
            }

            $origmsgRecord = Message::query()
                ->where('id', $origmsg)
                ->where(function ($query) use ($sender) {
                    $query->where('receiver', $sender->id)->orWhere('sender', $sender->id);
                })
                ->first();

            if (! $origmsgRecord) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_no_permission_forwarding'] ?? 'No permission to forward.');
            }
            if ($origmsgRecord === null) {
                throw new LogicException('Expected non-null original message record.');
            }

            $to = trim((string) $request->input('to', ''));
            if ($to === '') {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_must_enter_username'] ?? 'You must enter a username.');
            }

            $receiver = UserDisplay::userIdFromName($to);
            if ($receiver <= 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_user_not_exist'] ?? 'No user with that name.');
            }

            $locale = Locale::userLocale($receiver);
            $origSenderName = (int) $origmsgRecord->sender === 0
                ? Locale::trans('message.msg_system', [], $locale)
                : '[url=userdetails.php?id='.$origmsgRecord->sender.']'.UserDisplay::plainUsername($origmsgRecord->sender).'[/url]';

            $body = '-------- '.Locale::trans('message.msg_original_message_from', [], $locale).$origSenderName." --------\n"
                .$origmsgRecord->msg."\n\n"
                .($body ? '-------- [url=userdetails.php?id='.$sender->id.']'.$sender->username.'[/url][i] Wrote at '.date('Y-m-d H:i:s').":[/i] --------\n".$body : '');
        } else {
            $receiver = (int) $request->input('receiver', 0);
            if ($receiver <= 0 || ($origmsg > 0 && ! Validators::isId($origmsg))) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_invalid_id'] ?? 'Invalid ID.');
            }

            if ($body === '') {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_please_enter_something'] ?? 'Please enter something.');
            }
        }

        if (! Permission::can(PermissionEnum::STAFF_MEMBER, $sender)) {
            $lastPm = $sender->last_pm;
            $lastPmTs = $lastPm ? strtotime($lastPm) : false;
            if ($lastPmTs !== false && $lastPmTs > (time() - 10)) {
                $secs = 60 - (time() - $lastPmTs);
                LegacyResponse::abort($lang['std_error'] ?? 'Error', ($lang['std_message_flooding_denied'] ?? 'Message flooding not allowed. Please wait ').$secs.($lang['std_before_sending_pm'] ?? ' second(s) before sending PM.'));
            }
        }

        $recipient = User::query()->find($receiver);
        if (! $recipient) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_user_not_exist'] ?? 'No user with that ID.');
        }
        if (! $recipient instanceof User) {
            throw new LogicException('Expected recipient to be a User instance.');
        }

        if (! Permission::can(PermissionEnum::STAFF_MEMBER, $sender)) {
            if ($recipient->parked) {
                LegacyResponse::abort($lang['std_refused'] ?? 'Refused', $lang['std_account_parked'] ?? 'Account is parked.');
            }

            if ($recipient->acceptpms === 'yes') {
                $blocked = DB::table('blocks')
                    ->where('userid', $recipient->id)
                    ->where('blockid', $sender->id)
                    ->count() > 0;
                if ($blocked) {
                    LegacyResponse::abort($lang['std_refused'] ?? 'Refused', $lang['std_user_blocks_your_pms'] ?? 'User blocks your PMs.');
                }
            } elseif ($recipient->acceptpms === 'friends') {
                $isFriend = DB::table('friends')
                    ->where('userid', $recipient->id)
                    ->where('friendid', $sender->id)
                    ->count() > 0;
                if (! $isFriend) {
                    LegacyResponse::abort($lang['std_refused'] ?? 'Refused', $lang['std_user_accepts_friends_pms'] ?? 'User accepts PMs from friends only.');
                }
            } elseif ($recipient->acceptpms === 'no') {
                LegacyResponse::abort($lang['std_refused'] ?? 'Refused', $lang['std_user_blocks_all_pms'] ?? 'User blocks all PMs.');
            }
        }

        $message = Message::add([
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
            $orig = Message::query()->find($origmsg);
            if ($orig && $orig->receiver == $sender->id) {
                if ($orig->saved === 'no') {
                    $orig->delete();
                } else {
                    $orig->update(['location' => '0']);
                }
            }
        }

        $redirect = SafeReturnUrl::filter($returnto, '/messages.php');

        return redirect($redirect);
    }

    public function deletemessage(Request $request): RedirectResponse
    {
        $sender = Auth::user();
        if (! $sender instanceof User) {
            $lang = $this->lang('deletemessage');
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_message_id'] ?? 'Bad message ID.');
        }
        if (! $sender instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }

        $lang = $this->lang('deletemessage');

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_message_id'] ?? 'Bad message ID.');
        }

        $type = (string) $request->input('type', '');

        if ($type === 'in') {
            $msg = Message::query()->where('id', $id)->first(['id', 'receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || $msg->receiver != $sender->id) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_suggested'] ?? 'Not suggested.');
            }
            if ($msg === null) {
                throw new LogicException('Expected non-null message.');
            }

            if ((int) $msg->location === 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_in_inbox'] ?? 'Not in inbox.');
            }

            if ($msg->saved === 'yes') {
                $msg->update(['location' => '0', 'unread' => false]);
            } else {
                $msg->delete();
            }

            Cache::clearInboxCount($sender->id);
        } elseif ($type === 'out') {
            $msg = Message::query()->where('id', $id)->first(['id', 'receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || $msg->sender != $sender->id) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_suggested'] ?? 'Not suggested.');
            }
            if ($msg === null) {
                throw new LogicException('Expected non-null message.');
            }

            if ((int) $msg->location === 0 && $msg->saved === 'no') {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_in_sentbox'] ?? 'Not in sentbox.');
            }

            if ((int) $msg->location === 0) {
                $msg->delete();
            } else {
                $msg->update(['saved' => false]);
            }

            Cache::forgetWithLocales('user_'.$sender->id.'_outbox_count');
        } else {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_unknown_pm_type'] ?? 'Unknown PM type.');
        }

        $redirect = $type === 'out' ? 'messages.php?out=1' : 'messages.php';

        return redirect($redirect);
    }

    /**
     * @return array<string, string>
     */
    private function lang(string $name): array
    {
        return array_merge(
            (array) app(Language::class)->functions(),
            (array) (app(Globals::class)->get('lang_'.$name) ?? [])
        );
    }

    private function sendPmNotification(User $recipient, User $sender, string $subject, int $messageId): void
    {
        $locale = Locale::userLocale($recipient->id);
        $siteConfig = SiteConfig::current();
        $siteName = $siteConfig->basic->siteName();
        $siteEmail = $siteConfig->main->siteEmail();

        $baseUrl = rtrim($siteConfig->basic->baseUrl(), '/');
        if ($baseUrl === '') {
            $baseUrl = Url::schemeAndHost();
        } else {
            // BASEURL config value is scheme-less — prepend the
            // detected scheme so links work on HTTPS deployments.
            $baseUrl = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        }
        $messageUrl = $baseUrl.'/messages.php?action=viewmessage&id='.$messageId;

        $title = $siteName.' '.Locale::trans('message.mail_received_pm_from', [], $locale).$sender->username.'!';
        $body = Locale::trans('message.mail_dear', [], $locale).$recipient->username.",\n\n".
            Locale::trans('message.mail_you_received_a_pm', [], $locale)."\n\n".
            Locale::trans('message.mail_sender', [], $locale).': '.$sender->username."\n".
            Locale::trans('message.mail_subject', [], $locale).': '.$subject."\n".
            Locale::trans('message.mail_date', [], $locale).': '.date('Y-m-d H:i:s')."\n\n".
            Locale::trans('message.mail_use_following_url', [], $locale).
            "<b><a href=\"javascript:void(null)\" onclick=\"window.open('".$messageUrl."')\">".
            Locale::trans('message.mail_here', [], $locale).'</a></b>'.
            Locale::trans('message.mail_use_following_url_1', [], $locale).'<br />'.
            $messageUrl."\n\n".
            '------'.Locale::trans('message.mail_yours', [], $locale)."\n".
            sprintf(Locale::trans('message.mail_the_site_team', [], $locale), $siteName);

        Mail::sentLegacy(
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

    private function handleMessagesAction(Request $request): ?RedirectResponse
    {
        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = (string) $request->input('action', 'viewmailbox');
        }

        if ($action === 'viewmessage') {
            $id = (int) $request->input('id', 0);
            $user = Auth::user();
            if ($id <= 0 || ! $user instanceof User || ! app(MessageRepository::class)->getMessageForUser($id, (int) $user->id)) {
                return redirect('/messages.php');
            }

            return null;
        }

        if ($action === 'moveordel') {
            if (! $request->isMethod('post')) {
                return redirect('/messages.php');
            }

            return $this->handleMoveOrDel($request);
        }

        if ($action === 'editmailboxes2') {
            if (! $request->isMethod('post')) {
                return redirect('/messages.php');
            }

            return $this->handleEditMailboxes($request);
        }

        if ($action === 'deletemessage') {
            if (! $request->isMethod('post')) {
                return redirect('/messages.php');
            }

            return $this->handleDeleteMessage($request);
        }

        return null;
    }

    private function handleMoveOrDel(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $pmId = (int) $request->input('id', 0);
        $pmBox = (int) $request->input('box', 0);
        /** @var array<int, mixed> $pmMessages */
        $pmMessages = (array) $request->input('messages', []);

        if ($request->has('markread')) {
            if ($pmId > 0) {
                $updated = app(MessageRepository::class)->markAsRead($pmId, $userId);
            } else {
                if ($pmMessages === []) {
                    $lang = (array) app(Language::class)->functions();
                    LegacyResponse::abort('Error', (string) ($lang['select_at_least_one_record'] ?? 'Please select at least one record.'));
                }
                $updated = app(MessageRepository::class)->markAsRead($pmMessages, $userId);
            }
            Cache::clearInboxCount($userId);
            if ($updated == 0) {
                $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_cannot_mark_messages'] ?? 'Cannot mark messages.'));
            }

            return redirect("/messages.php?action=viewmailbox&box={$pmBox}");
        }

        if ($request->has('move')) {
            if ($pmId > 0) {
                $updated = app(MessageRepository::class)->moveMessages($pmId, $userId, $pmBox);
            } else {
                $updated = app(MessageRepository::class)->moveMessages($pmMessages, $userId, $pmBox);
            }
            if ($updated == 0) {
                $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_cannot_move_messages'] ?? 'Cannot move messages.'));
            }
            Cache::clearInboxCount($userId);
            Cache::forgetWithLocales('user_'.$userId.'_outbox_count');

            return redirect("/messages.php?action=viewmailbox&box={$pmBox}");
        }

        if ($request->has('delete')) {
            if ($pmId > 0) {
                $deletedCount = app(MessageRepository::class)->deleteSingleMessage($pmId, $userId) ? 1 : 0;
            } else {
                if ($pmMessages === []) {
                    $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
                    LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_no_message_selected'] ?? 'No message selected.'));
                }
                $deletedCount = app(MessageRepository::class)->deleteMultipleMessages($pmMessages, $userId);
            }
            Cache::clearInboxCount($userId);
            Cache::forgetWithLocales('user_'.$userId.'_outbox_count');
            if ($deletedCount == 0) {
                $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_cannot_delete_messages'] ?? 'Cannot delete messages.'));
            }

            return redirect('/messages.php?action=viewmailbox');
        }

        $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
        LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_no_action'] ?? 'No action.'));

        return redirect('/messages.php');
    }

    private function handleEditMailboxes(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $action2 = (string) $request->input('action2', '');
        $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);

        if ($action2 === 'add') {
            app(MessageRepository::class)->addMailboxes($userId, [
                $request->input('new1'),
                $request->input('new2'),
                $request->input('new3'),
            ]);

            return redirect('/messages.php?action=editmailboxes');
        }

        if ($action2 === 'edit') {
            $pmBoxes = app(MessageRepository::class)->getUserMailboxes($userId);
            if ($pmBoxes->isEmpty()) {
                LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['text_no_mailboxes_to_edit'] ?? 'No mailboxes to edit.'));
            }
            foreach ($pmBoxes as $pmBox) {
                $newValue = (string) ($request->input('edit'.$pmBox->id) ?? '');
                if ($newValue !== '' && $newValue !== $pmBox->name) {
                    app(MessageRepository::class)->updateMailbox($userId, (int) $pmBox->id, $newValue);
                } elseif ($newValue === '') {
                    app(MessageRepository::class)->deleteMailbox($userId, (int) $pmBox->id, (int) $pmBox->boxnumber);
                }
            }

            return redirect('/messages.php?action=editmailboxes');
        }

        LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_no_action'] ?? 'No action.'));

        return redirect('/messages.php');
    }

    private function handleDeleteMessage(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $pmId = (int) $request->input('id', 0);
        $message = app(MessageRepository::class)->deleteSingleMessage($pmId, $userId);
        if (! $message) {
            $lang = (array) (app(Globals::class)->get('lang_messages') ?? []);
            LegacyResponse::abort((string) ($lang['std_error'] ?? 'Error'), (string) ($lang['std_no_message_id'] ?? 'No message ID.'));
        }
        if ($message === null) {
            throw new LogicException('Expected non-null message.');
        }

        Cache::clearInboxCount($userId);
        Cache::forgetWithLocales('user_'.$userId.'_outbox_count');

        return redirect('/messages.php?action=viewmailbox&id='.(int) ($message['location'] ?? 0));
    }
}
