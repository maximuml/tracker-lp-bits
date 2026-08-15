<?php

declare(strict_types=1);

namespace App\Services\Legacy;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\User;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Mail;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Url;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

/**
 * Bridge for legacy message pages.
 *
 * The inbox/outbox list still uses `messages_content.php` via the legacy renderer.
 * `takeMessage` and `deletemessage` have been moved into typed service methods so
 * the corresponding action partials no longer contain DB queries or side effects.
 */
final class MessageService
{
    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function messages(Request $request): array|RedirectResponse
    {
        $result = $this->renderMessages();
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return ['content' => $result->getContent()];
    }

    public function sendmessage(Request $request): Response|RedirectResponse
    {
        return $this->renderPartial('sendmessage');
    }

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

        $lang = $this->lang('takemessage');

        $origmsg = (int) $request->input('origmsg', 0);
        $body = trim((string) $request->input('body', ''));
        $isForward = $request->input('forward') === '1';
        $save = $request->input('save') === 'yes' ? 'yes' : 'no';
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

            $body = "-------- ".Locale::trans('message.msg_original_message_from', [], $locale).$origSenderName." --------\n"
                .$origmsgRecord->msg."\n\n"
                .($body ? "-------- [url=userdetails.php?id=".$sender->id."]".$sender->username."[/url][i] Wrote at ".date('Y-m-d H:i:s').":[/i] --------\n".$body : '');
        } else {
            $receiver = (int) $request->input('receiver', 0);
            if ($receiver <= 0 || ($origmsg > 0 && ! \App\Support\Validators::isId($origmsg))) {
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

        if (! Permission::can(PermissionEnum::STAFF_MEMBER, $sender)) {
            if ($recipient->parked === 'yes') {
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
        NexusDB::cache_del('user_'.$sender->id.'_outbox_count');

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

        $redirect = $returnto !== '' ? $returnto : 'messages.php';

        return redirect($redirect);
    }

    public function deletemessage(Request $request): RedirectResponse
    {
        $sender = Auth::user();
        if (! $sender instanceof User) {
            $lang = $this->lang('deletemessage');
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_message_id'] ?? 'Bad message ID.');
        }

        $lang = $this->lang('deletemessage');

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_bad_message_id'] ?? 'Bad message ID.');
        }

        $type = (string) $request->input('type', '');

        if ($type === 'in') {
            $msg = Message::query()->where('id', $id)->first(['receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || $msg->receiver != $sender->id) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_suggested'] ?? 'Not suggested.');
            }

            if ((int) $msg->location === 0) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_in_inbox'] ?? 'Not in inbox.');
            }

            if ($msg->saved === 'yes') {
                $msg->update(['location' => '0', 'unread' => 'no']);
            } else {
                $msg->delete();
            }

            Cache::clearInboxCount($sender->id);
        } elseif ($type === 'out') {
            $msg = Message::query()->where('id', $id)->first(['receiver', 'sender', 'location', 'saved', 'unread']);
            if (! $msg || $msg->sender != $sender->id) {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_suggested'] ?? 'Not suggested.');
            }

            if ((int) $msg->location === 0 && $msg->saved === 'no') {
                LegacyResponse::abort($lang['std_error'] ?? 'Error', $lang['std_not_in_sentbox'] ?? 'Not in sentbox.');
            }

            if ((int) $msg->location === 0) {
                $msg->delete();
            } else {
                $msg->update(['saved' => 'no']);
            }

            NexusDB::cache_del('user_'.$sender->id.'_outbox_count');
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
            (array) SupportContext::getLangFunctions(),
            (array) (SupportContext::getGlobal('lang_'.$name) ?? [])
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
            Locale::trans('message.mail_here', [], $locale)."</a></b>".
            Locale::trans('message.mail_use_following_url_1', [], $locale)."<br />".
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

    private function renderMessages(): Response|RedirectResponse
    {
        $path = __DIR__ . '/messages_content.php';

        if (! file_exists($path)) {
            return response('Legacy content missing: messages', 500);
        }

        ob_start();
        try {
            include $path;
        } catch (HttpResponseException $e) {
            ob_get_clean();

            throw $e;
        }

        $content = (string) ob_get_clean();

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url);
            }
        }

        return response($content);
    }

    private function renderPartial(string $name): Response|RedirectResponse
    {
        $path = __DIR__ . '/partials/' . $name . '.php';

        if (! file_exists($path)) {
            return response('Legacy partial missing: ' . $name, 500);
        }

        ob_start();
        try {
            include $path;
        } catch (HttpResponseException $e) {
            ob_get_clean();

            throw $e;
        }

        $content = (string) ob_get_clean();

        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $url = trim(substr($header, 9));
                header_remove('Location');

                return redirect($url);
            }
        }

        return response($content);
    }
}
