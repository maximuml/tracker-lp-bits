<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\DTOs\Message\StoreMessageDto;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class MessageRepository extends BaseRepository
{
    const STAFF_MESSAGE_TOTAL_CACHE_KEY = 'staff_message_count';

    const STAFF_MESSAGE_NEW_CACHE_KEY = 'staff_new_message_count';

    /**
     * @return Collection<int, \stdClass>
     */
    public static function getUserMailboxes(int $userId): Collection
    {
        return DB::table('pmboxes')
            ->where('userid', $userId)
            ->orderBy('boxnumber')
            ->get(['id', 'boxnumber', 'name']);
    }

    public static function getMailboxName(int $userId, int $mailbox): ?string
    {
        return DB::table('pmboxes')
            ->where('userid', $userId)
            ->where('boxnumber', $mailbox)
            ->value('name');
    }

    /**
     * @return array{count: int, messages: \Illuminate\Database\Eloquent\Collection<int, Message>}
     */
    public static function getMailboxMessages(int $userId, int $mailbox, string $keyword, string $place, ?string $unread, int $offset, int $perPage): array
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
        if ($unread === 'yes' || $unread === 'no') {
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
            $countQuery->where('sender', $userId)->where('saved', 'yes');
            $messages = (clone $query)
                ->where('sender', $userId)
                ->where('saved', 'yes')
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($perPage)
                ->get();
        }

        return ['count' => (int) $countQuery->count(), 'messages' => $messages];
    }

    public static function getMessageForUser(int $messageId, int $userId): ?Message
    {
        return Message::query()
            ->where('id', $messageId)
            ->where(function ($q) use ($userId) {
                $q->where('receiver', $userId)
                    ->orWhere(function ($sub) use ($userId) {
                        $sub->where('sender', $userId)->where('saved', 'yes');
                    });
            })
            ->first();
    }

    public static function getMessageForForward(int $messageId, int $userId): ?Message
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
    public static function markAsRead(int|array $ids, int $userId): int
    {
        return Message::query()->whereIn('id', (array) $ids)->where('receiver', $userId)->update(['unread' => 'no']);
    }

    /**
     * @param  int|array<int>  $ids
     */
    public static function moveMessages(int|array $ids, int $userId, int $box): int
    {
        return Message::query()->whereIn('id', (array) $ids)->where('receiver', $userId)->update(['location' => $box]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function deleteSingleMessage(int $messageId, int $userId): ?array
    {
        $message = Message::query()->where('id', $messageId)->first();
        if (! $message) {
            return null;
        }

        $messageArr = $message->toArray();
        if ($messageArr['receiver'] == $userId && $messageArr['saved'] == 'no') {
            $message->delete();
        } elseif ($messageArr['sender'] == $userId && $messageArr['location'] == 0) { // PM_DELETED
            $message->delete();
        } elseif ($messageArr['receiver'] == $userId && $messageArr['saved'] == 'yes') {
            $message->update(['location' => 0]);
        } elseif ($messageArr['sender'] == $userId && $messageArr['location'] != 0) { // not PM_DELETED
            $message->update(['saved' => 'no']);
        } else {
            return null;
        }

        return $messageArr;
    }

    /**
     * @param  array<int>  $ids
     */
    public static function deleteMultipleMessages(array $ids, int $userId): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            if (self::deleteSingleMessage((int) $id, $userId) !== null) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public static function getNextMailboxNumber(int $userId): int
    {
        $max = (int) DB::table('pmboxes')->where('userid', $userId)->max('boxnumber');

        return max(1, $max);
    }

    /**
     * @param  array<int|string, mixed>  $names
     */
    public static function addMailboxes(int $userId, array $names): void
    {
        $box = self::getNextMailboxNumber($userId);
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $box++;
            DB::table('pmboxes')->insert(['userid' => $userId, 'name' => $name, 'boxnumber' => $box]);
        }
    }

    public static function updateMailbox(int $userId, int $boxId, string $newName): void
    {
        DB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->update(['name' => $newName]);
    }

    public static function deleteMailbox(int $userId, int $boxId, int $boxNumber): void
    {
        DB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->delete();
        Message::query()->where('saved', 'yes')->where('location', $boxNumber)->where('receiver', $userId)->update(['location' => 0]);
        Message::query()->where('saved', 'yes')->where('sender', $userId)->update(['saved' => 'no']);
        Message::query()->where('saved', 'no')->where('location', $boxNumber)->where('receiver', $userId)->delete();
        Message::query()->where('location', 0)->where('saved', 'yes')->where('sender', $userId)->delete();
    }

    public static function getUsername(int $userId): ?string
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

    /**
     * @param  mixed  $uid
     * @param  mixed  $answered
     */
    public static function countStaffMessage($uid, $answered = null): int
    {
        return self::buildStaffMessageQuery($uid, $answered)->count();
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $answered
     * @return Builder<StaffMessage>
     */
    public static function buildStaffMessageQuery($uid, $answered = null): Builder
    {
        $query = StaffMessage::query();
        if ($answered !== null) {
            $query->where('answered', $answered);
        }
        if (! Permission::can(PermissionEnum::STAFF_MEMBER, User::findOrFail((int) $uid))) {
            // Not staff member only can see authorized
            $permissions = ToolRepository::listUserAllPermissions($uid);
            $query->whereIn('permission', $permissions);
        }

        return $query;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $type
     * @param  mixed  $value
     * @return mixed
     */
    public static function updateStaffMessageCountCache($uid = 0, $type = '', $value = '')
    {
        if ($uid === false) {
            Cache::forgetWithLocales(self::STAFF_MESSAGE_NEW_CACHE_KEY);
            Cache::forgetWithLocales(self::STAFF_MESSAGE_TOTAL_CACHE_KEY);
        } else {
            $redis = NexusDB::redis();
            match ($type) {
                'total' => $redis->hSet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, $uid, $value),
                'new' => $redis->hSet(self::STAFF_MESSAGE_NEW_CACHE_KEY, $uid, $value),
                default => throw new \InvalidArgumentException("Invalid type: $type")
            };
        }
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $type
     * @return mixed
     */
    public static function getStaffMessageCountCache($uid = 0, $type = '')
    {
        $redis = NexusDB::redis();

        return match ($type) {
            'total' => $redis->hGet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, $uid),
            'new' => $redis->hGet(self::STAFF_MESSAGE_NEW_CACHE_KEY, $uid),
            default => throw new \InvalidArgumentException("Invalid type: $type")
        };
    }

    public static function getLastPmId(int $userId): int
    {
        return (int) (Message::query()->where('receiver', $userId)->max('id') ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getUnreadPmNotifications(int $userId, int $lastPmId, int $limit): array
    {
        $rows = Message::query()
            ->where('receiver', $userId)
            ->where('unread', 'yes')
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
