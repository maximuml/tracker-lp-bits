<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MailboxRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\RequestValues;
use App\Support\Time;
use App\Support\UserDisplay;
use App\ViewModels\Message\MessageBoxOption;
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

    private ?NexusCache $cache;

    public function __construct(
        MessageRepository $messageRepository,
        MailboxRepository $mailboxRepository,
        CurrentUser $currentUser,
        ?NexusCache $cache,
    ) {
        $this->messageRepository = $messageRepository;
        $this->mailboxRepository = $mailboxRepository;
        $this->currentUser = $currentUser;
        $this->cache = $cache;
    }

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): MessagePageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $userId = $this->currentUser->id();

        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = 'viewmailbox';
        }

        $data = [
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost'),
            'contentWidth' => '737',
        ];

        switch ($action) {
            case 'viewmessage':
                $viewmessage = $this->buildViewMessage($curUser, $userId, $request);
                $data['viewmessage'] = $viewmessage;
                $listRequest = $request->duplicate(['box' => $viewmessage['mailbox']]);
                $data['viewmailbox'] = $this->buildViewMailbox($curUser, $userId, $listRequest);
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
                    __('messages.std_error'),
                    __('messages.std_invalid_mailbox')
                );
            }
            $mailboxName = htmlspecialchars((string) $pmBoxName);
        } elseif ($mailbox === self::PM_INBOX) {
            $mailboxName = __('messages.text_inbox');
        } else {
            $mailboxName = __('messages.text_sentbox');
        }

        $senderReceiver = $mailbox !== self::PM_SENT_BOX
            ? __('messages.text_sender')
            : __('messages.text_receiver');

        // Search params
        $keyword = trim((string) $request->input('keyword', ''));
        $place = (string) $request->input('place', '');
        $unreadRaw = $request->input('unread');
        $unreadBool = match (true) {
            $unreadRaw === 'yes' || $unreadRaw === '1' => true,
            $unreadRaw === 'no' || $unreadRaw === '0' => false,
            default => null,
        };
        $perpage = (int) ($this->currentUser->value('pmnum', 0)) ?: 20;

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

        UserDisplay::preload($messages->map(fn ($message) => (int) ($mailbox !== self::PM_SENT_BOX ? $message->sender : $message->receiver))->all());
        // Build message rows
        $rows = [];
        foreach ($messages as $message) {
            $row = $message->toArray();
            if ((int) ($row['sender'] ?? 0) !== 0) {
                if ($mailbox !== self::PM_SENT_BOX) {
                    $username = UserDisplay::username((int) ($row['sender'] ?? 0));
                } else {
                    $username = UserDisplay::username((int) ($row['receiver'] ?? 0));
                }
            } else {
                $username = (string) __('messages.text_system');
            }

            $subject = (string) ($row['subject'] ?? '');
            if (strlen($subject) <= 0) {
                $subject = __('messages.text_no_subject');
            }

            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'subject' => $subject,
                'username' => SafeHtml::fromTrustedHtml($username),
                'added' => SafeHtml::fromTrustedHtml((string) Time::format((string) ($row['added'] ?? ''), true, false)),
                'unread' => (bool) ($row['unread'] ?? false),
            ];
        }

        // User mailboxes for the "move to" select
        $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);
        $moveBoxes = [];
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $moveBoxes[] = new MessageBoxOption((int) $boxArr['boxnumber'], (string) $boxArr['name']);
        }

        // Jump-to boxes for the search form
        $jumpToBoxes = $this->buildJumpToBoxOptions($pmBoxes, $mailbox);

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
            'moveBoxes' => $moveBoxes,
            'jumpToBoxes' => $jumpToBoxes,
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
     * Build the jump-to box options for the search form.
     *
     * @param  Collection<int, \stdClass>  $pmBoxes
     * @return list<MessageBoxOption>
     */
    private function buildJumpToBoxOptions(Collection $pmBoxes, int $selected): array
    {
        $options = [
            new MessageBoxOption(self::PM_INBOX, __('messages.select_inbox'), $selected === self::PM_INBOX),
            new MessageBoxOption(self::PM_SENT_BOX, __('messages.select_sentbox'), $selected === self::PM_SENT_BOX),
        ];
        foreach ($pmBoxes as $row) {
            $rowArr = (array) $row;
            $boxnumber = (int) $rowArr['boxnumber'];
            $options[] = new MessageBoxOption($boxnumber, (string) $rowArr['name'], $boxnumber === $selected);
        }

        return $options;
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
                __('messages.std_error'),
                __('messages.std_no_permission')
            );
        }

        $messageModel = $this->messageRepository->getMessageForUser($pmId, $userId);
        if (! $messageModel) {
            LegacyResponse::abort(
                __('messages.std_error'),
                __('messages.std_no_permission')
            );
        }

        $message = $messageModel->toArray();

        $isSender = (int) ($message['sender'] ?? 0) === $userId;
        $replyHref = null;

        if ($isSender) {
            $sender = UserDisplay::username((int) ($message['receiver'] ?? 0));
            $from = __('messages.text_to');
        } else {
            $from = __('messages.text_from');
            if ((int) ($message['sender'] ?? 0) === 0) {
                $sender = (string) __('messages.text_system');
            } else {
                $sender = UserDisplay::username((int) ($message['sender'] ?? 0));
                $replyHref = '/web/sendmessage?receiver='.(int) ($message['sender'] ?? 0).'&replyto='.$pmId;
            }
        }

        $body = $this->renderMessageBody((string) ($message['msg'] ?? ''), true);
        $added = (string) ($message['added'] ?? '');

        $showUnread = $isSender && (bool) ($message['unread'] ?? false);

        $subject = (string) ($message['subject'] ?? '');
        if (strlen($subject) <= 0) {
            $subject = __('messages.text_no_subject');
        }

        // Mark message as read
        $this->messageRepository->markAsRead($pmId, $userId);
        if ($this->cache !== null) {
            $this->cache->forget('user_'.$userId.'_unread_message_count', true);
        }

        // Mailbox for menu highlight
        $mailbox = $isSender ? self::PM_SENT_BOX : (int) ($message['location'] ?? self::PM_INBOX);

        // Move-to boxes
        $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);
        $moveBoxes = [];
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $moveBoxes[] = new MessageBoxOption((int) $boxArr['boxnumber'], (string) $boxArr['name']);
        }

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'from' => $from,
            'sender' => SafeHtml::fromTrustedHtml($sender),
            'added' => SafeHtml::fromTrustedHtml((string) Time::format($added, true, false)),
            'showUnread' => $showUnread,
            'body' => SafeHtml::fromTrustedHtml($body),
            'replyHref' => $replyHref,
            'isSender' => $isSender,
            'mailbox' => $mailbox,
            'moveBoxes' => $moveBoxes,
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
                __('messages.std_error'),
                __('messages.std_no_permission_forwarding')
            );
        }

        $message = $messageModel->toArray();

        $subject = 'Fwd: '.htmlspecialchars((string) ($message['subject'] ?? ''));
        $from = (int) ($message['receiver'] ?? 0);
        $orig = (int) ($message['sender'] ?? 0);

        $fromName = UserDisplay::username($from);
        if ($orig === 0) {
            $origName = (string) __('messages.text_system');
            $origName2 = __('messages.text_system');
        } else {
            $origName = UserDisplay::username($orig);
            $origName2 = $this->messageRepository->getUsername($orig) ?? '';
        }

        $body = '-------- Original Message from '.htmlspecialchars($origName2).' --------<br />'.$this->renderMessageBody((string) ($message['msg'] ?? ''));

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'fromName' => SafeHtml::fromTrustedHtml($fromName),
            'origName' => SafeHtml::fromTrustedHtml($origName),
            'body' => SafeHtml::fromTrustedHtml($body),
        ];
    }

    private function renderMessageBody(string $text, bool $stripHtml = false): SafeHtml
    {
        $key = 'fmt_pm_'.md5($text).($stripHtml ? '_s' : '');
        $cached = $this->cache?->get($key);
        if (is_string($cached)) {
            return SafeHtml::fromTrustedHtml($cached);
        }
        $html = Format::formatComment($text, $stripHtml);
        $this->cache?->put($key, (string) $html, 86400);

        return $html;
    }
}
