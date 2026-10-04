<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Message;

/**
 * Read-side lookups on the messages table (MessageRepository is at the
 * public-method cap; these finds have their own home here).
 */
final class MessageLookupRepository extends BaseRepository
{
    public function findById(int $id): ?Message
    {
        return Message::query()->find($id);
    }

    /** @param  array<int, string>  $fields */
    public function findByIdFields(int $id, array $fields): ?Message
    {
        return Message::query()->where('id', $id)->first($fields);
    }

    /**
     * A message the given user is a party to (either direction) — the
     * replied-to lookup the send flow uses before quoting.
     */
    public function findVisibleToUser(int $id, int $userId): ?Message
    {
        return Message::query()
            ->where('id', $id)
            ->where(function ($query) use ($userId): void {
                $query->where('receiver', $userId)->orWhere('sender', $userId);
            })
            ->first();
    }

    public function countUnreadFor(int $receiverId): int
    {
        return Message::query()->where('receiver', $receiverId)->where('unread', true)->count();
    }
}
