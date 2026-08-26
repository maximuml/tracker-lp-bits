<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Message;
use App\Repositories\MessageRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Prepares section data for the messages page, replacing the legacy
 * messages_content.php partial with typed Blade-rendered sections.
 *
 * Sections:
 *  - viewmailbox: inbox/sentbox/custom mailbox list with search + pagination
 *  - viewmessage: single message view with reply/forward/delete links
 *  - forward: forward-a-PM form
 *  - editmailboxes: add/edit custom mailboxes form
 */
final class MessagePageService
{
    private const PM_INBOX = 1;

    private const PM_SENT_BOX = -1;

    /**
     * Build the data for the requested action.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (SupportContext::getUser() ?? []);
        $lang = (array) (SupportContext::getGlobal('lang_messages') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = (string) $request->input('action', 'viewmailbox');
        }
        if ($action === '') {
            $action = 'viewmailbox';
        }

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => (string) SupportContext::getGlobal('BASEURL', ''),
            'contentWidth' => (string) SupportContext::getGlobal('CONTENT_WIDTH', '737'),
        ];

        switch ($action) {
            case 'viewmessage':
                $data['viewmessage'] = $this->buildViewMessage($lang, $curUser, $userId, $request);
                break;
            case 'forward':
                $data['forward'] = $this->buildForward($lang, $userId, $request);
                break;
            case 'editmailboxes':
                $data['editmailboxes'] = $this->buildEditMailboxes($lang, $userId);
                break;
            default:
                $data['viewmailbox'] = $this->buildViewMailbox($lang, $curUser, $userId, $request);
                $data['action'] = 'viewmailbox';
                break;
        }

        return $data;
    }

    /**
     * Build the mailbox listing section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewMailbox(array $lang, array $curUser, int $userId, Request $request): array
    {
        $mailbox = (int) ($request->input('box', 0) ?: self::PM_INBOX);
        if ($mailbox === 0) {
            $mailbox = self::PM_INBOX;
        }

        // Mailbox name
        if ($mailbox !== self::PM_INBOX && $mailbox !== self::PM_SENT_BOX) {
            $pmBoxName = MessageRepository::getMailboxName($userId, $mailbox);
            if (! $pmBoxName) {
                LegacyResponse::abort(
                    (string) ($lang['std_error'] ?? 'Error'),
                    (string) ($lang['std_invalid_mailbox'] ?? 'Invalid mailbox.')
                );
            }
            $mailboxName = htmlspecialchars((string) $pmBoxName);
        } elseif ($mailbox === self::PM_INBOX) {
            $mailboxName = (string) ($lang['text_inbox'] ?? 'Inbox');
        } else {
            $mailboxName = (string) ($lang['text_sentbox'] ?? 'Sentbox');
        }

        $senderReceiver = $mailbox !== self::PM_SENT_BOX
            ? (string) ($lang['text_sender'] ?? 'Sender')
            : (string) ($lang['text_receiver'] ?? 'Receiver');

        // Search params
        $keyword = trim((string) $request->input('keyword', ''));
        $place = (string) $request->input('place', '');
        $unread = $request->input('unread');
        $unreadStr = is_string($unread) ? $unread : null;
        $perpage = (int) ($curUser['pmnum'] ?? 0) ?: 20;

        $countResult = MessageRepository::getMailboxMessages($userId, $mailbox, $keyword, $place, $unreadStr, 0, 0);
        $count = $countResult['count'];

        $pagerHref = '?action=viewmailbox'
            .'&box='.$mailbox
            .($place ? '&place='.$place : '')
            .($keyword ? '&keyword='.rawurlencode($keyword) : '')
            .($unread ? '&unread='.$unread : '')
            .'&';

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $pagerHref);

        $messageResult = MessageRepository::getMailboxMessages($userId, $mailbox, $keyword, $place, $unreadStr, (int) $offset, (int) $perpage);
        $messages = $messageResult['messages'];

        // Build message rows
        $rows = [];
        foreach ($messages as $message) {
            $row = $message->toArray();
            if ((int) $row['sender'] !== 0) {
                if ($mailbox !== self::PM_SENT_BOX) {
                    $username = UserDisplay::username((int) $row['sender']);
                } else {
                    $username = UserDisplay::username((int) $row['receiver']);
                }
            } else {
                $username = (string) ($lang['text_system'] ?? 'System');
            }

            $subject = htmlspecialchars((string) $row['subject']);
            if (strlen($subject) <= 0) {
                $subject = (string) ($lang['text_no_subject'] ?? 'No subject');
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'subject' => $subject,
                'username' => $username,
                'added' => Time::format((string) $row['added'], true, false),
                'unread' => (string) $row['unread'],
            ];
        }

        // User mailboxes for the "move to" select
        $pmBoxes = MessageRepository::getUserMailboxes($userId);
        $moveBoxOptions = '';
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $moveBoxOptions .= '<option value="'.(int) $boxArr['boxnumber'].'">'.htmlspecialchars((string) $boxArr['name'])."</option>\n";
        }

        // Jump-to boxes for the search form
        $jumpToBoxes = $this->buildJumpToBoxes($pmBoxes, $mailbox);

        return [
            'mailbox' => $mailbox,
            'mailboxName' => $mailboxName,
            'senderReceiver' => $senderReceiver,
            'isSentBox' => $mailbox === self::PM_SENT_BOX,
            'keyword' => htmlspecialchars($keyword),
            'place' => $place,
            'unread' => is_string($unread) ? $unread : '',
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'hasMessages' => $messages->isNotEmpty(),
            'moveBoxOptions' => $moveBoxOptions,
            'jumpToBoxes' => $jumpToBoxes,
            'jumpToSelected' => $mailbox,
        ];
    }

    /**
     * Build the single message view section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewMessage(array $lang, array $curUser, int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);
        if ($pmId <= 0) {
            LegacyResponse::abort(
                (string) ($lang['std_error'] ?? 'Error'),
                (string) ($lang['std_no_permission'] ?? 'No permission.')
            );
        }

        $messageModel = MessageRepository::getMessageForUser($pmId, $userId);
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
            $unread = ($message['unread'] ?? '') === 'yes'
                ? '<span style="color: #FF0000;"><b>'.htmlspecialchars((string) ($lang['text_new'] ?? 'New')).'</b></a>'
                : '';
        }

        $subject = htmlspecialchars((string) $message['subject']);
        if (strlen($subject) <= 0) {
            $subject = (string) ($lang['text_no_subject'] ?? 'No subject');
        }

        // Mark message as read
        MessageRepository::markAsRead($pmId, $userId);
        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('user_'.$userId.'_unread_message_count');
        }

        // Mailbox for menu highlight
        $mailbox = $isSender ? self::PM_SENT_BOX : (int) $message['location'];

        // Move-to boxes
        $pmBoxes = MessageRepository::getUserMailboxes($userId);
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
    private function buildForward(array $lang, int $userId, Request $request): array
    {
        $pmId = (int) $request->input('id', 0);

        $messageModel = MessageRepository::getMessageForForward($pmId, $userId);
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
            $origName2 = MessageRepository::getUsername($orig) ?? '';
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

    /**
     * Build the edit-mailboxes section.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildEditMailboxes(array $lang, int $userId): array
    {
        $pmBoxes = MessageRepository::getUserMailboxes($userId);

        $boxes = [];
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $boxes[] = [
                'id' => (int) $boxArr['id'],
                'name' => htmlspecialchars((string) $boxArr['name']),
            ];
        }

        return [
            'boxes' => $boxes,
            'hasBoxes' => $pmBoxes->count() > 0,
        ];
    }

    /**
     * Build the jump-to box options HTML for the search form.
     *
     * @param  Collection<int, \stdClass>  $pmBoxes
     */
    private function buildJumpToBoxes(Collection $pmBoxes, int $selected): string
    {
        $lang = (array) (SupportContext::getGlobal('lang_messages') ?? []);
        $html = '<option value="1" '.($selected === self::PM_INBOX ? ' selected' : '').'>'.htmlspecialchars((string) ($lang['select_inbox'] ?? 'Inbox'))."</option>\n";
        $html .= '<option value="-1" '.($selected === self::PM_SENT_BOX ? ' selected' : '').'>'.htmlspecialchars((string) ($lang['select_sentbox'] ?? 'Sentbox'))."</option>\n";
        foreach ($pmBoxes as $row) {
            $rowArr = (array) $row;
            $sel = (int) $rowArr['boxnumber'] === $selected ? ' selected' : '';
            $html .= '<option value="'.(int) $rowArr['boxnumber'].$sel.'">'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }

        return $html;
    }
}
