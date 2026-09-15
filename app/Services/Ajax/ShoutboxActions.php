<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\DTOs\Auth\ActorContext;
use App\Services\ShoutboxService;
use App\Support\Shoutbox;

final class ShoutboxActions
{
    /** @var array<int, string> */
    public const ACTIONS = [
        'clearShoutBox',
        'shoutboxPost',
        'shoutboxEdit',
        'shoutboxDelete',
        'shoutboxReact',
    ];

    public function __construct(
        private readonly ShoutboxService $shoutboxService,
        private readonly ActorContext $actorContext,
    ) {}

    /** @param array<string, mixed> $params */
    public function clearShoutBox(array $params): mixed
    {
        $actor = $this->actorContext;
        if (! $this->shoutboxService->clearAll($actor)) {
            throw new \RuntimeException('No permission');
        }

        return true;
    }

    /** @param array<string, mixed> $params */
    public function shoutboxPost(array $params): mixed
    {
        $actor = $this->actorContext;
        $text = trim((string) ($params['text'] ?? $params['content'] ?? ''));
        if ($text === '') {
            throw new \InvalidArgumentException('Message cannot be empty');
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException('Message too long');
        }
        if (! $this->shoutboxService->postMessage($actor, $text)) {
            throw new \RuntimeException('Speaking too often or no permission');
        }

        return true;
    }

    /** @param array<string, mixed> $params */
    public function shoutboxEdit(array $params): mixed
    {
        $actor = $this->actorContext;
        $id = (int) ($params['id'] ?? 0);
        $text = trim((string) ($params['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            throw new \InvalidArgumentException('Invalid input');
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException('Message too long');
        }
        if (! $this->shoutboxService->editMessage($actor, $id, $text)) {
            throw new \RuntimeException('Message not found, no permission, edit window expired, or editing too often');
        }

        return true;
    }

    /** @param array<string, mixed> $params */
    public function shoutboxDelete(array $params): mixed
    {
        $actor = $this->actorContext;
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid input');
        }
        if (! $this->shoutboxService->deleteMessage($actor, $id)) {
            throw new \RuntimeException('No permission, delete window expired, or deleting too often');
        }

        return true;
    }

    /** @param array<string, mixed> $params */
    public function shoutboxReact(array $params): mixed
    {
        $actor = $this->actorContext;
        $id = (int) ($params['id'] ?? 0);
        $reaction = (string) ($params['reaction'] ?? '');
        $result = $this->shoutboxService->toggleReaction($actor, $id, $reaction);
        if ($result === null) {
            throw new \InvalidArgumentException('Invalid reaction or reacting too often');
        }

        return $result;
    }
}
