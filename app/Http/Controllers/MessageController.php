<?php

namespace App\Http\Controllers;

use App\DTOs\Message\ListUnreadDto;
use App\DTOs\Message\MessageListDto;
use App\DTOs\Message\StoreMessageDto;
use App\DTOs\Message\UpdateMessageDto;
use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\Legacy\MessageService;
use App\Services\MessagePageService;
use App\Support\CurrentUser;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MessageController extends LegacyController
{
    private MessageRepository $repository;

    private MessageService $legacyService;

    private MessagePageService $pageService;

    public function __construct(MessageRepository $repository, MessageService $legacyService, MessagePageService $pageService)
    {
        $this->repository = $repository;
        $this->legacyService = $legacyService;
        $this->pageService = $pageService;
    }

    public function messages(Request $request): View|RedirectResponse
    {
        $actionRedirect = $this->legacyService->handleMessagesActionPublic($request);
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        $data = $this->pageService->build($request);

        return $this->legacyPage($request, 'messages', true, $data);
    }

    public function sendmessage(Request $request): Response|RedirectResponse|View
    {
        $langSendmessage = (array) (SupportContext::getGlobal('lang_sendmessage') ?? []);

        $receiver = (int) $request->input('receiver', 0);
        if ($receiver <= 0) {
            return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
        }

        $replyto = $request->input('replyto');
        if ($replyto !== null && $replyto !== '' && ! Validators::isId($replyto)) {
            return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
        }
        $replyto = $replyto !== null && $replyto !== '' ? (int) $replyto : 0;

        $user = User::query()->find($receiver);
        if (! $user) {
            return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_no_user_id'] ?? 'No user with that ID.');
        }

        $subject = '';
        $body = '';
        if ($replyto > 0) {
            $msg = Message::query()->find($replyto);
            if (! $msg) {
                return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
            }
            $msga = $msg->toArray();
            $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
            if ((int) ($msga['receiver'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
                return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
            }
            $body .= $msga['msg']."\n\n-------- [url=userdetails.php?id=".$currentUser['id'].']'.$currentUser['username'].'[/url][i] Wrote at '.date('Y-m-d H:i:s').":[/i] --------\n";
            $subject = (string) $msga['subject'];
            if (preg_match('/^Re:\\s/', $subject)) {
                $subject = preg_replace('/^Re:\\s(.*)$/', 'Re(2): \\1', $subject) ?? $subject;
            } elseif (preg_match('/^Re\\([0-9]*\\):\\s/', $subject)) {
                $replycount = (int) preg_replace('/^Re\\(([0-9]*)\\):\\s/', '\\1', $subject);
                $replycount++;
                $subject = preg_replace('/^Re\\(([0-9]*)\\):\\s(.*)$/', 'Re('.$replycount.'): \\2', $subject) ?? $subject;
            } else {
                $subject = 'Re: '.$subject;
            }
            $subject = htmlspecialchars($subject);
        }

        $returnto = '';
        if ($request->input('returnto') !== null && $request->input('returnto') !== '') {
            $returnto = htmlspecialchars((string) $request->input('returnto'));
        } elseif ($request->headers->get('referer') !== null) {
            $returnto = htmlspecialchars((string) $request->headers->get('referer'));
        }

        $title = ($langSendmessage['text_message_to'] ?? 'Message to ').UserDisplay::username($receiver);

        return $this->legacyPageRaw($request, 'sendmessage', true, [
            'receiver' => $receiver,
            'replyto' => $replyto,
            'subject' => $subject,
            'body' => $body,
            'returnto' => $returnto,
            'title' => $title,
        ]);
    }

    public function takeMessage(Request $request): Response|RedirectResponse
    {
        return $this->legacyService->takeMessage($request);
    }

    public function deletemessage(Request $request): Response|RedirectResponse
    {
        return $this->legacyService->deletemessage($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $dto = MessageListDto::fromRequest($request, (int) Auth::id());

        $result = MessageRepository::getMailboxMessages(
            $dto->userId,
            $dto->mailbox,
            $dto->keyword,
            $dto->place,
            $dto->unread,
            $dto->offset(),
            $dto->perPage
        );

        $paginator = new LengthAwarePaginator(
            $result['messages'],
            $result['count'],
            $dto->perPage,
            $dto->page,
            ['path' => $request->url()]
        );

        return $this->success(MessageResource::collection($paginator));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $message = $this->repository->store(StoreMessageDto::fromRequest($request));
        $message->load('send_user');

        return $this->success(new MessageResource($message), 'Message sent');
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Message $message): array
    {
        $userId = (int) Auth::id();

        if (! MessageRepository::getMessageForUser((int) $message->id, $userId)) {
            abort(404);
        }

        if ($message->receiver == $userId && $message->unread === 'yes') {
            MessageRepository::markAsRead([(int) $message->id], $userId);
            $message->refresh();
        }

        $message->load('send_user');

        return $this->success(new MessageResource($message));
    }

    /**
     * @return array<string, mixed>
     */
    public function update(Request $request, Message $message): array
    {
        $userId = (int) Auth::id();

        if (! MessageRepository::getMessageForUser((int) $message->id, $userId)) {
            abort(404);
        }

        $dto = UpdateMessageDto::fromRequest($request);

        if ($dto->unread !== null) {
            Message::query()->where('id', (int) $message->id)->where(function ($q) use ($userId) {
                $q->where('receiver', $userId)->orWhere('sender', $userId);
            })->update(['unread' => $dto->unread]);
        }

        if ($dto->location !== null) {
            MessageRepository::moveMessages([(int) $message->id], $userId, $dto->location);
        }

        $message->refresh()->load('send_user');

        return $this->success(new MessageResource($message));
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Message $message): array
    {
        $userId = (int) Auth::id();
        if (MessageRepository::deleteSingleMessage((int) $message->id, $userId) === null) {
            abort(404);
        }

        return $this->success(['success' => true], 'Message deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function listUnread(Request $request): array
    {
        $dto = ListUnreadDto::fromRequest($request);

        $messages = Message::query()
            ->where('receiver', Auth::id())
            ->where('unread', 'yes')
            ->with('send_user')
            ->orderByDesc('id')
            ->paginate($dto->perPage);

        return $this->success(MessageResource::collection($messages));
    }
}
