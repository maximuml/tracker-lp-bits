<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Services\Legacy\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Support\SupportContext;
use App\Support\UserDisplay;

class MessageController extends LegacyController
{
    private MessageRepository $repository;

    private MessageService $legacyService;

    public function __construct(MessageRepository $repository, MessageService $legacyService)
    {
        $this->repository = $repository;
        $this->legacyService = $legacyService;
    }

    public function messages(Request $request): View|RedirectResponse
    {
        $result = $this->legacyService->messages($request);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->legacyPage($request, 'messages', true, $result);
    }

    public function sendmessage(Request $request): Response|RedirectResponse|View
    {
        $langSendmessage = (array) (SupportContext::getGlobal('lang_sendmessage') ?? []);

        $receiver = (int) $request->input('receiver', 0);
        if ($receiver <= 0) {
            return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
        }

        $replyto = $request->input('replyto');
        if ($replyto !== null && $replyto !== '' && ! \App\Support\Validators::isId($replyto)) {
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
            $currentUser = (array) (SupportContext::getUser() ?? []);
            if ((int) ($msga['receiver'] ?? 0) !== (int) ($currentUser['id'] ?? 0)) {
                return $this->legacyAbortResponse($langSendmessage['std_error'] ?? 'Error', $langSendmessage['std_permission_denied'] ?? 'Permission denied.');
            }
            $body .= $msga['msg'] . "\n\n-------- [url=userdetails.php?id=" . $currentUser['id'] . "]" . $currentUser['username'] . "[/url][i] Wrote at " . date("Y-m-d H:i:s") . ":[/i] --------\n";
            $subject = (string) $msga['subject'];
            if (preg_match('/^Re:\\s/', $subject)) {
                $subject = preg_replace('/^Re:\\s(.*)$/', 'Re(2): \\1', $subject) ?? $subject;
            } elseif (preg_match('/^Re\\([0-9]*\\):\\s/', $subject)) {
                $replycount = (int) preg_replace('/^Re\\(([0-9]*)\\):\\s/', '\\1', $subject);
                $replycount++;
                $subject = preg_replace('/^Re\\(([0-9]*)\\):\\s(.*)$/', 'Re(' . $replycount . '): \\2', $subject) ?? $subject;
            } else {
                $subject = 'Re: ' . $subject;
            }
            $subject = htmlspecialchars($subject);
        }

        $returnto = '';
        if ($request->input('returnto') !== null && $request->input('returnto') !== '') {
            $returnto = htmlspecialchars((string) $request->input('returnto'));
        } elseif ($request->headers->get('referer') !== null) {
            $returnto = htmlspecialchars((string) $request->headers->get('referer'));
        }

        $title = ($langSendmessage['text_message_to'] ?? 'Message to ') . UserDisplay::username($receiver);

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
     * @return  array<string, mixed>
     */
    public function index(Request $request): array
    {
        $userId = (int) Auth::id();
        $mailbox = (int) $request->input('mailbox', 0);
        $unread = $request->input('unread');
        if ($unread !== null && ! in_array((string) $unread, ['yes', 'no'], true)) {
            $unread = null;
        }

        $keyword = (string) $request->input('keyword', '');
        $place = (string) $request->input('place', '');

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        $result = MessageRepository::getMailboxMessages($userId, $mailbox, $keyword, $place, $unread === null ? null : (string) $unread, $offset, $perPage);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $result['messages'],
            $result['count'],
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        return $this->success(MessageResource::collection($paginator));
    }

    /**
     * @return  array<string, mixed>
     */
    public function store(Request $request): array
    {
        $validated = $request->validate([
            'receiver' => 'required|integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'msg' => 'required|string',
        ]);

        $validated['sender'] = Auth::id();
        $validated['added'] = now();
        $validated['unread'] = 'yes';
        $validated['location'] = 0;
        $validated['saved'] = 'no';

        $message = $this->repository->store($validated);
        $message->load('send_user');

        return $this->success(new MessageResource($message), 'Message sent');
    }

    /**
     * @param  \App\Models\Message  $message
     * @return  array<string, mixed>
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Message  $message
     * @return  array<string, mixed>
     */
    public function update(Request $request, Message $message): array
    {
        $userId = (int) Auth::id();

        if (! MessageRepository::getMessageForUser((int) $message->id, $userId)) {
            abort(404);
        }

        $validated = $request->validate([
            'unread' => 'sometimes|in:yes,no',
            'location' => 'sometimes|integer',
        ]);

        if (isset($validated['unread'])) {
            Message::query()->where('id', (int) $message->id)->where(function ($q) use ($userId) {
                $q->where('receiver', $userId)->orWhere('sender', $userId);
            })->update(['unread' => (string) $validated['unread']]);
        }

        if (isset($validated['location'])) {
            MessageRepository::moveMessages([(int) $message->id], $userId, (int) $validated['location']);
        }

        $message->refresh()->load('send_user');

        return $this->success(new MessageResource($message));
    }

    /**
     * @param  \App\Models\Message  $message
     * @return  array<string, mixed>
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
     * @return  array<string, mixed>
     */
    public function listUnread(Request $request): array
    {
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $messages = Message::query()
            ->where('receiver', Auth::id())
            ->where('unread', 'yes')
            ->with('send_user')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->success(MessageResource::collection($messages));
    }
}
