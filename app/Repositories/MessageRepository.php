<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Message\StoreMessageDto;
use App\Events\MessageCreated;
use App\Models\Message;
use App\Models\User;
use App\Support\Cache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Message repository: PM reads, read state, deletion, and generic CRUD.
 */
class MessageRepository extends BaseRepository
{
    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'sender', 'receiver', 'added', 'subject'];
    }

    /**
     * @return array{count: int, messages: EloquentCollection<int, Message>}
     */
    public function getMailboxMessages(int $userId, int $mailbox, string $keyword, string $place, ?bool $unread, int $offset, int $perPage): array
    {
        $query = Message::query()->with('send_user');
        if ($keyword !== '') {
            switch ($place) {
                case 'body':
                    $query->where('msg', 'like', '%'.$keyword.'%');
                    break;
                case 'title':
                    $query->where('subject', 'like', '%'.$keyword.'%');
                    break;
                default:
                    $query->where(function ($q) use ($keyword) {
                        $q->where('msg', 'like', '%'.$keyword.'%')
                            ->orWhere('subject', 'like', '%'.$keyword.'%');
                    });
            }
        }
        if ($unread !== null) {
            $query->where('unread', $unread);
        }

        if ($mailbox != -1) { // PM_SENTBOX
            $countQuery = clone $query;
            $countQuery->where('receiver', $userId)->where('location', $mailbox);
            $messages = (clone $query)
                ->where('receiver', $userId)
                ->where('location', $mailbox)
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($perPage)
                ->get();
        } else {
            $countQuery = clone $query;
            $countQuery->where('sender', $userId)->where('saved', true);
            $messages = (clone $query)
                ->where('sender', $userId)
                ->where('saved', true)
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($perPage)
                ->get();
        }

        return ['count' => (int) $countQuery->count(), 'messages' => $messages];
    }

    public function getMessageForUser(int $messageId, int $userId): ?Message
    {
        return Message::query()
            ->where('id', $messageId)
            ->where(function ($q) use ($userId) {
                $q->where('receiver', $userId)
                    ->orWhere(function ($sub) use ($userId) {
                        $sub->where('sender', $userId)->where('saved', true);
                    });
            })
            ->first();
    }

    public function getMessageForForward(int $messageId, int $userId): ?Message
    {
        return Message::query()
            ->where('id', $messageId)
            ->where(function ($q) use ($userId) {
                $q->where('receiver', $userId)->orWhere('sender', $userId);
            })
            ->first();
    }

    /**
     * @param  int|array<int>  $ids
     */
    public function markAsRead(int|array $ids, int $userId): int
    {
        return Message::query()->whereIn('id', (array) $ids)->where('receiver', $userId)->update(['unread' => false]);
    }

    /**
     * @param  int|array<int>  $ids
     */
    public function moveMessages(int|array $ids, int $userId, int $box): int
    {
        return Message::query()->whereIn('id', (array) $ids)->where('receiver', $userId)->update(['location' => $box]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deleteSingleMessage(int $messageId, int $userId): ?array
    {
        $message = Message::query()->where('id', $messageId)->first();
        if (! $message) {
            return null;
        }

        $messageArr = $message->toArray();
        if (($messageArr['receiver'] ?? 0) == $userId && ($messageArr['saved'] ?? 0) == 0) {
            $message->delete();
        } elseif (($messageArr['sender'] ?? 0) == $userId && ($messageArr['location'] ?? 0) == 0) { // PM_DELETED
            $message->delete();
        } elseif (($messageArr['receiver'] ?? 0) == $userId && ($messageArr['saved'] ?? 0) == 1) {
            $message->update(['location' => 0]);
        } elseif (($messageArr['sender'] ?? 0) == $userId && ($messageArr['location'] ?? 0) != 0) { // not PM_DELETED
            $message->update(['saved' => false]);
        } else {
            return null;
        }

        return $messageArr;
    }

    /**
     * @param  array<int>  $ids
     */
    public function deleteMultipleMessages(array $ids, int $userId): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->deleteSingleMessage((int) $id, $userId) !== null) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function getUsername(int $userId): ?string
    {
        return User::query()->where('id', $userId)->value('username');
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = Message::query();
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    public function store(StoreMessageDto $dto): Message
    {
        return Message::query()->create($dto->toArray());
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return mixed
     */
    public function update(array $params, $id)
    {
        $model = Message::query()->findOrFail((int) $id);
        /** @var array<string, mixed> $params */
        $model->update($params);

        return $model;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getDetail($id)
    {
        $model = Message::query()->findOrFail((int) $id);

        return $model;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id)
    {
        $model = Message::query()->findOrFail((int) $id);
        $result = $model->delete();

        return $result;
    }

    public function findById(int $id): ?Message
    {
        return Message::query()->find($id);
    }

    public function setUnreadForUser(int $messageId, int $userId, mixed $unread): int
    {
        return Message::query()->where('id', $messageId)->where(function ($q) use ($userId) {
            $q->where('receiver', $userId)->orWhere('sender', $userId);
        })->update(['unread' => $unread]);
    }

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    public function paginateUnread(int $userId, int $perPage)
    {
        return Message::query()
            ->where('receiver', $userId)
            ->where('unread', true)
            ->with('send_user')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUnreadPmNotifications(int $userId, int $lastPmId, int $limit, bool $oldestFirst = false): array
    {
        $rows = Message::query()
            ->where('receiver', $userId)
            ->where('unread', true)
            ->where('id', '>', $lastPmId)
            ->with('send_user')
            ->select('messages.*')
            ->addSelect(DB::raw('UNIX_TIMESTAMP(messages.added) as ts'))
            ->orderBy('id', $oldestFirst ? 'asc' : 'desc')
            ->limit($limit)
            ->get();

        $notifications = [];
        foreach ($rows as $row) {
            $notifications[] = [
                'id' => 'pm_'.$row->id,
                'type' => 'pm',
                'title' => (string) __('notifications.title_pm'),
                'body' => $row->subject,
                'from' => (string) ($row->send_user->username ?? 'System'),
                'url' => '/web/messages?action=viewmessage&id='.$row->id,
                'timestamp' => (int) $row->getAttribute('ts'),
            ];
        }

        return $notifications;
    }

    /**
     * Send a PM: clear the receiver's inbox-count cache, create the row, fire
     * the event. Mirrors the former `Message::add()` static helper.
     *
     * @param  array<string, mixed>  $data
     */
    public function add(array $data): Message
    {
        Cache::clearInboxCount((int) $data['receiver']);
        $message = Message::query()->create($data);
        event(new MessageCreated($message));

        return $message;
    }

    /**
     * Bulk-insert PM rows for mass messaging — deliberately skips the
     * per-message cache clear and event that `add()` fires.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    /**
     * @param  array<int, array<int|string, mixed>>  $rows
     */
    public function insertMessages(array $rows): bool
    {
        return Message::query()->insert($rows);
    }

    /**
     * System messages (sender IS NULL) older than the cutoff — the 180-day
     * housekeeping sweep.
     */
    public function deleteOldSystemMessages(string $before): int
    {
        return Message::query()->whereNull('sender')->where('added', '<', $before)->delete();
    }
}
