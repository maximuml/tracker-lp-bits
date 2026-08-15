<?php
namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Setting;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class MessageRepository extends BaseRepository
{
    const STAFF_MESSAGE_TOTAL_CACHE_KEY = 'staff_message_count';

    const STAFF_MESSAGE_NEW_CACHE_KEY = 'staff_new_message_count';

    /**
     * @return  \Illuminate\Support\Collection<int, \stdClass>
     */
    public static function getUserMailboxes(int $userId): \Illuminate\Support\Collection
    {
        return NexusDB::table('pmboxes')
            ->where('userid', $userId)
            ->orderBy('boxnumber')
            ->get(['id', 'boxnumber', 'name']);
    }

    public static function getMailboxName(int $userId, int $mailbox): ?string
    {
        return NexusDB::table('pmboxes')
            ->where('userid', $userId)
            ->where('boxnumber', $mailbox)
            ->value('name');
    }

    /**
     * @return  array{count: int, messages: \Illuminate\Database\Eloquent\Collection<int, Message>}
     */
    public static function getMailboxMessages(int $userId, int $mailbox, string $keyword, string $place, ?string $unread, int $offset, int $perPage): array
    {
        $query = Message::query();
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
     * @return  array<string, mixed>|null
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
        $max = (int) NexusDB::table('pmboxes')->where('userid', $userId)->max('boxnumber');

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
            NexusDB::table('pmboxes')->insert(['userid' => $userId, 'name' => $name, 'boxnumber' => $box]);
        }
    }

    public static function updateMailbox(int $userId, int $boxId, string $newName): void
    {
        NexusDB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->update(['name' => $newName]);
    }

    public static function deleteMailbox(int $userId, int $boxId, int $boxNumber): void
    {
        NexusDB::table('pmboxes')->where('id', $boxId)->where('userid', $userId)->delete();
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
     * @return  mixed
     */
    public function getList(array $params)
    {
        $query = Message::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function store(array $params)
    {
        $model = Message::query()->create($params);
        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return  mixed
     */
    public function update(array $params, $id)
    {
        $model = Message::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function getDetail($id)
    {
        $model = Message::query()->findOrFail($id);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function delete($id)
    {
        $model = Message::query()->findOrFail($id);
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
     * @return  \Illuminate\Database\Eloquent\Builder<StaffMessage>
     */
    public static function buildStaffMessageQuery($uid, $answered = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = StaffMessage::query();
        if ($answered !== null) {
            $query->where('answered', $answered);
        }
        if (!Permission::can(PermissionEnum::STAFF_MEMBER, User::findOrFail($uid))) {
            //Not staff member only can see authorized
            $permissions = ToolRepository::listUserAllPermissions($uid);
            $query->whereIn('permission', $permissions);
        }
        return $query;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $type
     * @param  mixed  $value
     * @return  mixed
     */
    public static function updateStaffMessageCountCache($uid = 0, $type = '', $value = '')
    {
        if ($uid === false) {
            NexusDB::cache_del(self::STAFF_MESSAGE_NEW_CACHE_KEY);
            NexusDB::cache_del(self::STAFF_MESSAGE_TOTAL_CACHE_KEY);
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
     * @return  mixed
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
}
