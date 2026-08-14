<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Repositories\MessageRepository;
use App\Services\Legacy\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MessageController extends LegacyController
{
    private MessageRepository $repository;

    private MessageService $legacyService;

    public function __construct(MessageRepository $repository, MessageService $legacyService)
    {
        $this->repository = $repository;
        $this->legacyService = $legacyService;
    }

    public function messages(Request $request): Response|RedirectResponse
    {
        return $this->legacyService->messages($request);
    }

    public function sendmessage(Request $request): Response|RedirectResponse
    {
        return $this->legacyService->sendmessage($request);
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
        $list = $this->repository->getList($request->all());

        return $this->success(MessageResource::collection($list));
    }

    /**
     * @return  array<string, mixed>
     */
    public function store(Request $request): array
    {
        $validated = $request->validate([
            'receiver' => 'required|integer',
            'subject' => 'required|string|max:255',
            'msg' => 'required|string',
        ]);

        $validated['sender'] = Auth::id();
        $validated['added'] = now();

        $message = $this->repository->store($validated);

        return $this->success(new MessageResource($message), 'Message sent');
    }

    /**
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function show($id): array
    {
        $message = $this->repository->getDetail($id);

        return $this->success(new MessageResource($message));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function update(Request $request, $id): array
    {
        $message = $this->repository->getDetail($id);
        $validated = $request->validate([
            'subject' => 'sometimes|string|max:255',
            'msg' => 'sometimes|string',
        ]);

        if (!empty($validated)) {
            $message->update($validated);
        }

        return $this->success(new MessageResource($message->fresh()));
    }

    /**
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function destroy($id): array
    {
        $this->repository->delete($id);

        return $this->success(['success' => true], 'Message deleted');
    }

    /**
     * @return  array<string, mixed>
     */
    public function listUnread(): array
    {
        $messages = Message::query()
            ->where('receiver', Auth::id())
            ->where('unread', 'yes')
            ->paginate();

        return $this->success(MessageResource::collection($messages));
    }
}
