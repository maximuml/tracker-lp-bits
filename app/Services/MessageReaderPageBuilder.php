<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MailboxRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\LegacyResponse;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

/**
 * Builds the single-message view and forward-a-PM sections of the
 * messages page. Extracted from MessagePageService to keep both
 * classes under the 400-line ratchet.
 */
final class MessageReaderPageBuilder
{
    private const PM_SENT_BOX = -1;

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MailboxRepository $mailboxRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache,
    ) {}

    /**
     * Build the single message view section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewMessage(array $lang, array $curUser, int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);
        if ($pmId <= 0) {
            LegacyResponse::abort(
                (string) ($lang['std_error'] ?? 'Error'),
                (string) ($lang['std_no_permission'] ?? 'No permission.')
            );
        }

        $messageModel = $this->messageRepository->getMessageForUser($pmId, $userId);
        if (! $messageModel) {
            LegacyResponse::abort(
                (string) ($lang['std_error'] ?? 'Error'),
                (string) ($lang['std_no_permission'] ?? 'No permission.')
            );

            return [];
        }

        $message = $messageModel->toArray();

        $isSender = (int) $message['sender'] === $userId;

        if ($isSender) {
            $sender = UserDisplay::username((int) $message['receiver']);
            $reply = '';
            $from = (string) ($lang['text_to'] ?? 'To');
        } else {
            $from = (string) ($lang['text_from'] ?? 'From');
            if ((int) $message['sender'] === 0) {
                $sender = (string) ($lang['text_system'] ?? 'System');
                $reply = '';
            } else {
                $sender = UserDisplay::username((int) $message['sender']);
                $reply = ' [ <a href="sendmessage.php?receiver='.(int) $message['sender'].'&replyto='.$pmId.'">'.htmlspecialchars((string) ($lang['text_reply'] ?? 'Reply')).'</a> ]';
            }
        }

        $body = Format::formatComment((string) $message['msg'], true);
        $added = (string) $message['added'];

        $unread = '';
        if ($isSender) {
            $unread = (bool) ($message['unread'] ?? false)
                ? '<span style="color: #FF0000;"><b>'.htmlspecialchars((string) ($lang['text_new'] ?? 'New')).'</b></a>'
                : '';
        }

        $subject = (string) $message['subject'];
        if (strlen($subject) <= 0) {
            $subject = (string) ($lang['text_no_subject'] ?? 'No subject');
        }

        // Mark message as read
        $this->messageRepository->markAsRead($pmId, $userId);
        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('user_'.$userId.'_unread_message_count', true);
        }

        // Mailbox for menu highlight
        $mailbox = $isSender ? self::PM_SENT_BOX : (int) $message['location'];

        // Move-to boxes
        $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);
        $moveBoxOptions = '';
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $moveBoxOptions .= '<option value="'.(int) $boxArr['boxnumber'].'">'.htmlspecialchars((string) $boxArr['name'])."</option>\n";
        }

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'from' => $from,
            'sender' => $sender,
            'added' => Time::format($added, true, false),
            'unread' => $unread,
            'body' => $body,
            'reply' => $reply,
            'isSender' => $isSender,
            'mailbox' => $mailbox,
            'moveBoxOptions' => $moveBoxOptions,
        ];
    }

    /**
     * Build the forward-a-PM form section.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function buildForward(array $lang, int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);

        $messageModel = $this->messageRepository->getMessageForForward($pmId, $userId);
        if (! $messageModel) {
            LegacyResponse::abort(
                (string) ($lang['std_error'] ?? 'Error'),
                (string) ($lang['std_no_permission_forwarding'] ?? 'No permission to forward.')
            );

            return [];
        }

        $message = $messageModel->toArray();

        $subject = 'Fwd: '.htmlspecialchars((string) $message['subject']);
        $from = (int) $message['receiver'];
        $orig = (int) $message['sender'];

        $fromName = UserDisplay::username($from);
        if ($orig === 0) {
            $origName = (string) ($lang['text_system'] ?? 'System');
            $origName2 = (string) ($lang['text_system'] ?? 'System');
        } else {
            $origName = UserDisplay::username($orig);
            $origName2 = $this->messageRepository->getUsername($orig) ?? '';
        }

        $body = '-------- Original Message from '.htmlspecialchars($origName2).' --------<br />'.Format::formatComment((string) $message['msg']);

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'fromName' => $fromName,
            'origName' => $origName,
            'body' => $body,
        ];
    }
}
