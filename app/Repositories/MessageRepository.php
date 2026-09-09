<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\Message\StoreMessageDto;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
        if ($messageArr['receiver'] == $userId && $messageArr['saved'] == 0) {
            $message->delete();
        } elseif ($messageArr['sender'] == $userId && $messageArr['location'] == 0) { // PM_DELETED
            $message->delete();
        } elseif ($messageArr['receiver'] == $userId && $messageArr['saved'] == 1) {
            $message->update(['location' => 0]);
        } elseif ($messageArr['sender'] == $userId && $messageArr['location'] != 0) { // not PM_DELETED
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

    public function getLastPmId(int $userId): int
    {
        return (int) (Message::query()->where('receiver', $userId)->max('id') ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getUnreadPmNotifications(int $userId, int $lastPmId, int $limit): array
    {
        $rows = Message::query()
            ->where('receiver', $userId)
            ->where('unread', true)
            ->where('id', '>', $lastPmId)
            ->with('send_user')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $notifications = [];
        foreach ($rows as $row) {
            $notifications[] = [
                'id' => 'pm_'.$row->id,
                'type' => 'pm',
                'title' => 'New message',
                'body' => $row->subject,
                'from' => (string) ($row->send_user->username ?? 'System'),
                'url' => 'messages.php?action=viewmessage&id='.$row->id,
                'timestamp' => (int) strtotime((string) $row->added),
            ];
        }

        return $notifications;
    }
}
