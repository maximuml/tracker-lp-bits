<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Repositories\MailboxRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\MessageSubject;
use App\Support\PageResponses;
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

    private ActorContext $actor;

    private ?NexusCache $cache;

    public function __construct(
        MessageRepository $messageRepository,
        MailboxRepository $mailboxRepository,
        ActorContext $actor,
        ?NexusCache $cache,
    ) {
        $this->messageRepository = $messageRepository;
        $this->mailboxRepository = $mailboxRepository;
        $this->actor = $actor;
        $this->cache = $cache;
    }

    /**
     * Build the data for the requested action.
     */
    public function build(Request $request): MessagePageViewModel
    {
        $actor = $this->actor;
        $userId = $actor->id;

        $action = (string) $request->input('action', '');
        if ($action === '') {
            $action = 'viewmailbox';
        }

        $data = [
            'curUser' => $actor->toLegacyArray(),
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost'),
            'contentWidth' => '737',
        ];

        $mailboxes = $this->mailboxRepository->getUserMailboxes($userId);

        switch ($action) {
            case 'viewmessage':
                $viewmessage = $this->buildViewMessage($actor, $userId, $request, $mailboxes);
                $data['viewmessage'] = $viewmessage;
                $listRequest = $request->duplicate(['box' => $viewmessage['mailbox']]);
                $data['viewmailbox'] = $this->buildViewMailbox($actor, $userId, $listRequest, $mailboxes);
                break;
            case 'forward':
                $data['forward'] = $this->buildForward($userId, $request);
                break;
            case 'editmailboxes':
                $data['editmailboxes'] = $this->buildEditMailboxes($mailboxes);
                break;
            default:
                $data['viewmailbox'] = $this->buildViewMailbox($actor, $userId, $request, $mailboxes);
                $data['action'] = 'viewmailbox';
                break;
        }

        return new MessagePageViewModel(
            curUser: $data['curUser'],
            userId: $data['userId'],
            action: $data['action'],
            baseUrl: $data['baseUrl'],
            contentWidth: $data['contentWidth'],
            mailboxes: $mailboxes,
            viewmessage: $data['viewmessage'] ?? null,
            forward: $data['forward'] ?? null,
            editmailboxes: $data['editmailboxes'] ?? null,
            viewmailbox: $data['viewmailbox'] ?? null,
        );
    }

    /**
     * Build the mailbox listing section.
     *
     * @param  Collection<int, \stdClass>  $pmBoxes
     * @return array<string, mixed>
     */
    private function buildViewMailbox(ActorContext $actor, int $userId, Request $request, Collection $pmBoxes): array
    {
        $mailbox = (int) ($request->input('box', 0) ?: self::PM_INBOX);
        if ($mailbox === 0) {
            $mailbox = self::PM_INBOX;
        }

        // Mailbox name
        if ($mailbox !== self::PM_INBOX && $mailbox !== self::PM_SENT_BOX) {
            $pmBoxName = $this->mailboxRepository->getMailboxName($userId, $mailbox);
            if (! $pmBoxName) {
                PageResponses::abort(
                    __('messages.std_error'),
                    __('messages.std_invalid_mailbox')
                );
            }
            $mailboxName = (string) $pmBoxName;
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
        $perpage = (int) ($this->actor->value('pmnum', 0)) ?: 20;

        $count = $this->messageRepository->countMailboxMessages($userId, $mailbox, $keyword, $place, $unreadBool);

        $pagerHref = '?action=viewmailbox'
            .'&box='.$mailbox
            .($place ? '&place='.$place : '')
            .($keyword ? '&keyword='.rawurlencode($keyword) : '')
            .($unreadRaw ? '&unread='.$unreadRaw : '')
            .'&';

        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $pagerHref);

        $messages = $this->messageRepository->listMailboxMessages($userId, $mailbox, $keyword, $place, $unreadBool, (int) $offset, (int) $perpage);

        UserDisplay::preload($messages->map(fn ($message) => (int) ($mailbox !== self::PM_SENT_BOX ? $message->sender : $message->receiver))->all());
        // Build message rows
        $rows = [];
        foreach ($messages as $message) {
            $senderId = (int) ($message->sender ?? 0);
            if ($senderId !== 0) {
                if ($mailbox !== self::PM_SENT_BOX) {
                    $username = UserDisplay::username($senderId);
                } else {
                    $username = UserDisplay::username((int) ($message->receiver ?? 0));
                }
            } else {
                $username = SafeHtml::fromPlainText((string) __('messages.text_system'));
            }

            $subject = (string) ($message->subject ?? '');
            if (strlen($subject) <= 0) {
                $subject = __('messages.text_no_subject');
            }

            $rows[] = [
                'id' => (int) ($message->id ?? 0),
                'subject' => $subject,
                'username' => $username,
                'added' => Time::formatHtml((string) ($message->added?->format('Y-m-d H:i:s') ?? ''), true, false),
                'unread' => (bool) ($message->unread ?? false),
            ];
        }

        // User mailboxes for the "move to" select
        $moveBoxes = [];
        $mailboxTabs = [
            ['id' => self::PM_INBOX, 'label' => __('messages.text_inbox'), 'url' => '/web/messages'],
            ['id' => self::PM_SENT_BOX, 'label' => __('messages.text_sentbox'), 'url' => '/web/messages?action=viewmailbox&box=-1'],
        ];
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $boxnumber = (int) $boxArr['boxnumber'];
            $mailboxTabs[] = [
                'id' => $boxnumber,
                'label' => (string) $boxArr['name'],
                'url' => '/web/messages?action=viewmailbox&box='.$boxnumber,
            ];
            $moveBoxes[] = new MessageBoxOption($boxnumber, (string) $boxArr['name']);
        }
        $mailboxTabs[] = [
            'id' => 'manager',
            'label' => __('messages.text_mailbox_manager'),
            'url' => '/web/messages?action=editmailboxes',
        ];

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
            'mailboxTabs' => $mailboxTabs,
            'moveBoxes' => $moveBoxes,
            'jumpToBoxes' => $jumpToBoxes,
            'jumpToSelected' => $mailbox,
        ];
    }

    /**
     * Build the edit-mailboxes section.
     *
     * @param  Collection<int, \stdClass>  $pmBoxes
     * @return array<string, mixed>
     */
    private function buildEditMailboxes(Collection $pmBoxes): array
    {
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
     * @param  Collection<int, \stdClass>  $pmBoxes
     * @return array<string, mixed>
     */
    private function buildViewMessage(ActorContext $actor, int $userId, Request $request, Collection $pmBoxes): array
    {
        $pmId = (int) $request->input('id', 0);
        if ($pmId <= 0) {
            PageResponses::abort(
                __('messages.std_error'),
                __('messages.std_no_permission')
            );
        }

        $messageModel = $this->messageRepository->getMessageForUser($pmId, $userId);
        if (! $messageModel) {
            PageResponses::abort(
                __('messages.std_error'),
                __('messages.std_no_permission')
            );
        }

        $message = $messageModel->toArray();

        $isSender = (int) ($message['sender'] ?? 0) === $userId;
        $replyHref = null;
        $quickReply = null;
        $rawSubject = (string) ($message['subject'] ?? '');

        if ($isSender) {
            $sender = UserDisplay::username((int) ($message['receiver'] ?? 0));
            $from = __('messages.text_to');
        } else {
            $from = __('messages.text_from');
            if ((int) ($message['sender'] ?? 0) === 0) {
                $sender = SafeHtml::fromPlainText((string) __('messages.text_system'));
            } else {
                $senderId = (int) ($message['sender'] ?? 0);
                $sender = UserDisplay::username($senderId);
                $replyHref = '/web/sendmessage?receiver='.$senderId.'&replyto='.$pmId;
                $quickReply = [
                    'receiver' => $senderId,
                    'origmsg' => $pmId,
                    'subject' => MessageSubject::reply($rawSubject),
                    'save' => $actor->yes('savepms'),
                    'returnto' => '/web/messages?action=viewmessage&id='.$pmId,
                ];
            }
        }

        $body = $this->renderMessageBody((string) ($message['msg'] ?? ''), true);
        $added = (string) ($message['added'] ?? '');

        $showUnread = $isSender && (bool) ($message['unread'] ?? false);

        $subject = $rawSubject;
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
        $backHref = match ($mailbox) {
            self::PM_INBOX => '/web/messages',
            self::PM_SENT_BOX => '/web/messages?action=viewmailbox&box=-1',
            default => '/web/messages?action=viewmailbox&box='.$mailbox,
        };

        // Move-to boxes
        $moveBoxes = [];
        foreach ($pmBoxes as $box) {
            $boxArr = (array) $box;
            $moveBoxes[] = new MessageBoxOption((int) $boxArr['boxnumber'], (string) $boxArr['name']);
        }

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'from' => $from,
            'sender' => $sender,
            'added' => Time::formatHtml($added, true, false),
            'showUnread' => $showUnread,
            'body' => $body,
            'replyHref' => $replyHref,
            'quickReply' => $quickReply,
            'isSender' => $isSender,
            'mailbox' => $mailbox,
            'backHref' => $backHref,
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
            PageResponses::abort(
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
            $origName = SafeHtml::fromPlainText((string) __('messages.text_system'));
            $origName2 = __('messages.text_system');
        } else {
            $origName = UserDisplay::username($orig);
            $origName2 = $this->messageRepository->getUsername($orig) ?? '';
        }

        $body = '-------- Original Message from '.htmlspecialchars($origName2).' --------<br />'.$this->renderMessageBody((string) ($message['msg'] ?? ''), true);

        return [
            'pmId' => $pmId,
            'subject' => $subject,
            'fromName' => $fromName,
            'origName' => $origName,
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
