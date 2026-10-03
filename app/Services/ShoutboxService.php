<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\ShoutboxType;
use App\Support\Lock;
use App\Support\Shoutbox;

/**
 * Handles shoutbox message and reaction mutations.
 *
 * Read-side (history listing, SSE stream) stays in the controller and
 * ShoutboxRepository. This service owns all write operations: posting,
 * editing, deleting, clearing, and toggling reactions.
 */
final class ShoutboxService
{
    private const POST_LOCK_SECONDS = 60;

    private const EDIT_LOCK_SECONDS = 10;

    private const DELETE_LOCK_SECONDS = 10;

    private const REACT_LOCK_SECONDS = 5;

    public function __construct(private readonly ShoutboxRepositoryInterface $repository) {}

    /**
     * Post a new shoutbox message.
     *
     * @return bool True if the message was posted.
     */
    public function postMessage(ActorContext $actor, string $text): bool
    {
        $userId = $actor->id;
        if ($userId <= 0 || $text === '') {
            return false;
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            return false;
        }

        $lock = new Lock("shoutbox:{$userId}", self::POST_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }

        $this->repository->insertMessage([
            'userid' => $userId,
            'date' => time(),
            'text' => $text,
            'type' => ShoutboxType::SB->value,
        ]);

        return true;
    }

    /**
     * Delete a shoutbox message (and its reactions) by id.
     */
    public function deleteMessage(ActorContext $actor, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $msg = $this->repository->findById($id);
        if (! $msg) {
            return true;
        }

        $userId = $actor->id;
        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);

        if ($msgUserId !== $userId && ! $actor->can(PermissionEnum::SB_MANAGE)) {
            return false;
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! $actor->can(PermissionEnum::SB_MANAGE)) {
            return false;
        }

        $lock = new Lock('shoutbox_delete:'.$userId, self::DELETE_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }
        try {
            $this->repository->deleteWithReactions($id);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Edit a shoutbox message's text.
     */
    public function editMessage(ActorContext $actor, int $id, string $text): bool
    {
        $userId = $actor->id;
        if ($id <= 0 || $text === '') {
            return false;
        }
        if (mb_strlen($text) > Shoutbox::MAX_MESSAGE_LENGTH) {
            return false;
        }

        $msg = $this->repository->findById($id);
        if (! $msg) {
            return false;
        }

        $msgUserId = (int) ($msg->userid ?? 0);
        $msgDate = (int) ($msg->date ?? 0);

        if ($msgUserId !== $userId && ! $actor->can(PermissionEnum::SB_MANAGE)) {
            return false;
        }
        if ((time() - $msgDate) > Shoutbox::EDIT_WINDOW && ! $actor->can(PermissionEnum::SB_MANAGE)) {
            return false;
        }

        $lock = new Lock('shoutbox_edit:'.$userId, self::EDIT_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return false;
        }
        try {
            $this->repository->updateById($id, [
                'text' => $text,
                'edited_by' => $userId,
                'edited_at' => time(),
            ]);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Clear all shoutbox messages and reactions. Staff only.
     */
    public function clearAll(ActorContext $actor): bool
    {
        if (! $actor->can(PermissionEnum::SB_MANAGE)) {
            return false;
        }

        $this->repository->deleteAllWithReactions();

        return true;
    }

    /**
     * Toggle a reaction on a shoutbox message.
     *
     * @return string|null 'added', 'removed', or null on failure.
     */
    public function toggleReaction(ActorContext $actor, int $id, string $reaction): ?string
    {
        $userId = $actor->id;
        if ($id <= 0 || ! in_array($reaction, Shoutbox::REACTIONS, true)) {
            return null;
        }

        $lock = new Lock('shoutbox_react:'.$userId, self::REACT_LOCK_SECONDS);
        if (! $lock->acquire()) {
            return null;
        }
        try {
            return $this->repository->toggleReaction($id, $userId, $reaction) ? 'added' : 'removed';
        } finally {
            $lock->release();
        }
    }
}
