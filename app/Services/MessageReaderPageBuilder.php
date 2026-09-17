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
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildViewMessage(array $curUser, int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);
        if ($pmId <= 0) {
            LegacyResponse::abort(
                __('legacy/messages.std_error'),
                __('legacy/messages.std_no_permission')
            );
        }

        $messageModel = $this->messageRepository->getMessageForUser($pmId, $userId);
        if (! $messageModel) {
            LegacyResponse::abort(
                __('legacy/messages.std_error'),
                __('legacy/messages.std_no_permission')
            );

            return [];
        }

        $message = $messageModel->toArray();

        $isSender = (int) $message['sender'] === $userId;

        if ($isSender) {
            $sender = UserDisplay::username((int) $message['receiver']);
            $reply = '';
            $from = __('legacy/messages.text_to');
        } else {
            $from = __('legacy/messages.text_from');
            if ((int) $message['sender'] === 0) {
                $sender = __('legacy/messages.text_system');
                $reply = '';
            } else {
                $sender = UserDisplay::username((int) $message['sender']);
                $reply = ' [ <a href="sendmessage.php?receiver='.(int) $message['sender'].'&replyto='.$pmId.'">'.htmlspecialchars(__('legacy/messages.text_reply')).'</a> ]';
            }
        }

        $body = Format::formatComment((string) $message['msg'], true);
        $added = (string) $message['added'];

        $unread = '';
        if ($isSender) {
            $unread = (bool) ($message['unread'] ?? false)
                ? '<span style="color: #FF0000;"><b>'.htmlspecialchars(__('legacy/messages.text_new')).'</b></a>'
                : '';
        }

        $subject = (string) $message['subject'];
        if (strlen($subject) <= 0) {
            $subject = __('legacy/messages.text_no_subject');
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
     * @return array<string, mixed>
     */
    public function buildForward(int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);

        $messageModel = $this->messageRepository->getMessageForForward($pmId, $userId);
        if (! $messageModel) {
            LegacyResponse::abort(
                __('legacy/messages.std_error'),
                __('legacy/messages.std_no_permission_forwarding')
            );

            return [];
        }

        $message = $messageModel->toArray();

        $subject = 'Fwd: '.htmlspecialchars((string) $message['subject']);
        $from = (int) $message['receiver'];
        $orig = (int) $message['sender'];

        $fromName = UserDisplay::username($from);
        if ($orig === 0) {
            $origName = __('legacy/messages.text_system');
            $origName2 = __('legacy/messages.text_system');
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
