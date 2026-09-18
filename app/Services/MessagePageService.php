<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MailboxRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use App\ViewModels\MessagePageViewModel;
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
class MessagePageService
{
    private const PM_INBOX = 1;

    private const PM_SENT_BOX = -1;

    private MessageRepository $messageRepository;

    private MailboxRepository $mailboxRepository;

    private CurrentUser $currentUser;

    private Globals $globals;

    private ?LegacyRedisCache $legacyRedisCache;

    public function __construct(
        MessageRepository $messageRepository,
        MailboxRepository $mailboxRepository,
        CurrentUser $currentUser,
        Globals $globals,
        ?LegacyRedisCache $legacyRedisCache,
    ) {
        $this->messageRepository = $messageRepository;
        $this->mailboxRepository = $mailboxRepository;
        $this->currentUser = $currentUser;
        $this->globals = $globals;
        $this->legacyRedisCache = $legacyRedisCache;
    }

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): MessagePageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = 'viewmailbox';
        }

        $data = [
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => (string) $this->globals->get('BASEURL', ''),
            'contentWidth' => (string) $this->globals->get('CONTENT_WIDTH', '737'),
        ];

        switch ($action) {
            case 'viewmessage':
                $data['viewmessage'] = $this->buildViewMessage($curUser, $userId, $request);
                break;
            case 'forward':
                $data['forward'] = $this->buildForward($userId, $request);
                break;
            case 'editmailboxes':
                $data['editmailboxes'] = $this->buildEditMailboxes($userId);
                break;
            default:
                $data['viewmailbox'] = $this->buildViewMailbox($curUser, $userId, $request);
                $data['action'] = 'viewmailbox';
                break;
        }

        return new MessagePageViewModel(
            curUser: $data['curUser'],
            userId: $data['userId'],
            action: $data['action'],
            baseUrl: $data['baseUrl'],
            contentWidth: $data['contentWidth'],
            mailboxes: $this->mailboxRepository->getUserMailboxes($userId),
            viewmessage: $data['viewmessage'] ?? null,
            forward: $data['forward'] ?? null,
            editmailboxes: $data['editmailboxes'] ?? null,
            viewmailbox: $data['viewmailbox'] ?? null,
        );
    }

    /**
     * Build the mailbox listing section.
     *
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewMailbox(array $curUser, int $userId, Request $request): array
    {
        $mailbox = (int) ($request->input('box', 0) ?: self::PM_INBOX);
        if ($mailbox === 0) {
            $mailbox = self::PM_INBOX;
        }

        // Mailbox name
        if ($mailbox !== self::PM_INBOX && $mailbox !== self::PM_SENT_BOX) {
            $pmBoxName = $this->mailboxRepository->getMailboxName($userId, $mailbox);
            if (! $pmBoxName) {
                LegacyResponse::abort(
                    __('legacy/messages.std_error'),
                    __('legacy/messages.std_invalid_mailbox')
                );
            }
            $mailboxName = htmlspecialchars((string) $pmBoxName);
        } elseif ($mailbox === self::PM_INBOX) {
            $mailboxName = __('legacy/messages.text_inbox');
        } else {
            $mailboxName = __('legacy/messages.text_sentbox');
        }

        $senderReceiver = $mailbox !== self::PM_SENT_BOX
            ? __('legacy/messages.text_sender')
            : __('legacy/messages.text_receiver');

        // Search params
        $keyword = trim((string) $request->input('keyword', ''));
        $place = (string) $request->input('place', '');
        $unreadRaw = $request->input('unread');
        $unreadBool = match (true) {
            $unreadRaw === 'yes' || $unreadRaw === '1' => true,
            $unreadRaw === 'no' || $unreadRaw === '0' => false,
            default => null,
        };
        $perpage = (int) ($curUser['pmnum'] ?? 0) ?: 20;

        $countResult = $this->messageRepository->getMailboxMessages($userId, $mailbox, $keyword, $place, $unreadBool, 0, 0);
        $count = $countResult['count'];

        $pagerHref = '?action=viewmailbox'
            .'&box='.$mailbox
            .($place ? '&place='.$place : '')
            .($keyword ? '&keyword='.rawurlencode($keyword) : '')
            .($unreadRaw ? '&unread='.$unreadRaw : '')
            .'&';

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $pagerHref);

        $messageResult = $this->messageRepository->getMailboxMessages($userId, $mailbox, $keyword, $place, $unreadBool, (int) $offset, (int) $perpage);
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
                $username = (string) (__('legacy/messages.text_system'));
            }

            $subject = (string) $row['subject'];
            if (strlen($subject) <= 0) {
                $subject = (string) (__('legacy/messages.text_no_subject'));
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'subject' => $subject,
                'username' => SafeHtml::fromTrustedHtml($username),
                'added' => SafeHtml::fromTrustedHtml((string) Time::format((string) $row['added'], true, false)),
                'unread' => (bool) $row['unread'],
            ];
        }

        // User mailboxes for the "move to" select
        $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);
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
            'keyword' => $keyword,
            'place' => $place,
            'unread' => is_string($unreadRaw) ? $unreadRaw : '',
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'hasMessages' => $messages->isNotEmpty(),
            'moveBoxOptions' => SafeHtml::fromTrustedHtml($moveBoxOptions),
            'jumpToBoxes' => SafeHtml::fromTrustedHtml($jumpToBoxes),
            'jumpToSelected' => $mailbox,
        ];
    }

    /**
     * Build the edit-mailboxes section.
     *
     * @return array<string, mixed>
     */
    private function buildEditMailboxes(int $userId): array
    {
        $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);

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
        $html = '<option value="1" '.($selected === self::PM_INBOX ? ' selected' : '').'>'.htmlspecialchars(__('legacy/messages.select_inbox'))."</option>\n";
        $html .= '<option value="-1" '.($selected === self::PM_SENT_BOX ? ' selected' : '').'>'.htmlspecialchars(__('legacy/messages.select_sentbox'))."</option>\n";
        foreach ($pmBoxes as $row) {
            $rowArr = (array) $row;
            $sel = (int) $rowArr['boxnumber'] === $selected ? ' selected' : '';
            $html .= '<option value="'.(int) $rowArr['boxnumber'].$sel.'">'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }

        return $html;
    }

    /**
     * Build the single message view section.
     *
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildViewMessage(array $curUser, int $userId, Request $request): array
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
                $sender = (string) (__('legacy/messages.text_system'));
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
            $subject = (string) (__('legacy/messages.text_no_subject'));
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
            'sender' => SafeHtml::fromTrustedHtml($sender),
            'added' => SafeHtml::fromTrustedHtml((string) Time::format($added, true, false)),
            'unread' => SafeHtml::fromTrustedHtml($unread),
            'body' => SafeHtml::fromTrustedHtml($body),
            'reply' => SafeHtml::fromTrustedHtml($reply),
            'isSender' => $isSender,
            'mailbox' => $mailbox,
            'moveBoxOptions' => SafeHtml::fromTrustedHtml($moveBoxOptions),
        ];
    }

    /**
     * Build the forward-a-PM form section.
     *
     * @return array<string, mixed>
     */
    private function buildForward(int $userId, Request $request): array
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
            $origName = (string) (__('legacy/messages.text_system'));
            $origName2 = __('legacy/messages.text_system');
        } else {
            $origName = UserDisplay::username($orig);
            $origName2 = $this->messageRepository->getUsername($orig) ?? '';
        }

        $body = '-------- Original Message from '.htmlspecialchars($origName2).' --------<br />'.Format::formatComment((string) $message['msg']);

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'fromName' => SafeHtml::fromTrustedHtml($fromName),
            'origName' => SafeHtml::fromTrustedHtml($origName),
            'body' => SafeHtml::fromTrustedHtml($body),
        ];
    }
}
