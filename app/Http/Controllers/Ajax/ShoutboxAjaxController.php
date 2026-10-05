<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ajax;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\Ajax\ShoutboxClearRequest;
use App\Http\Requests\Ajax\ShoutboxDeleteRequest;
use App\Http\Requests\Ajax\ShoutboxEditRequest;
use App\Http\Requests\Ajax\ShoutboxPostRequest;
use App\Http\Requests\Ajax\ShoutboxReactRequest;
use App\Services\ShoutboxService;
use App\Support\Shoutbox;
use Illuminate\Http\JsonResponse;

final class ShoutboxAjaxController extends AjaxController
{
    public function __construct(
        private readonly ShoutboxService $shoutboxService,
        private readonly ActorContext $actorContext,
    ) {}

    public function clear(ShoutboxClearRequest $request): JsonResponse
    {
        return $this->respond(function () {
            if (! $this->shoutboxService->clearAll($this->actorContext)) {
                throw new \RuntimeException('No permission');
            }

            return true;
        });
    }

    public function post(ShoutboxPostRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $text = trim((string) $request->input('text', $request->input('content', '')));
            if ($text === '') {
                throw new \InvalidArgumentException('Message cannot be empty');
            }
            if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
                throw new \InvalidArgumentException('Message too long');
            }
            if (! $this->shoutboxService->postMessage($this->actorContext, $text)) {
                throw new \RuntimeException('Speaking too often or no permission');
            }

            return true;
        });
    }

    public function edit(ShoutboxEditRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $id = $request->integer('id');
            $text = trim((string) $request->input('text'));
            if ($text === '') {
                throw new \InvalidArgumentException('Invalid input');
            }
            if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
                throw new \InvalidArgumentException('Message too long');
            }
            if (! $this->shoutboxService->editMessage($this->actorContext, $id, $text)) {
                throw new \RuntimeException('Message not found, no permission, edit window expired, or editing too often');
            }

            return true;
        });
    }

    public function delete(ShoutboxDeleteRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            if (! $this->shoutboxService->deleteMessage($this->actorContext, $request->integer('id'))) {
                throw new \RuntimeException('No permission, delete window expired, or deleting too often');
            }

            return true;
        });
    }

    public function react(ShoutboxReactRequest $request): JsonResponse
    {
        return $this->respond(function () use ($request) {
            $result = $this->shoutboxService->toggleReaction(
                $this->actorContext,
                $request->integer('id'),
                (string) $request->input('reaction', ''),
            );
            if ($result === null) {
                throw new \InvalidArgumentException('Invalid reaction or reacting too often');
            }

            return $result;
        });
    }
}
