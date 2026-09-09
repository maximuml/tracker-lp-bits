<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redis;

/**
 * Staff message repository: staff message counts and cache management.
 */
class StaffMessageRepository extends BaseRepository
{
    const STAFF_MESSAGE_TOTAL_CACHE_KEY = 'staff_message_count';

    const STAFF_MESSAGE_NEW_CACHE_KEY = 'staff_new_message_count';

    /**
     * @param  mixed  $uid
     * @param  mixed  $answered
     */
    public function countStaffMessage($uid, $answered = null): int
    {
        return $this->buildStaffMessageQuery($uid, $answered)->count();
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $answered
     * @return Builder<StaffMessage>
     */
    public function buildStaffMessageQuery($uid, $answered = null): Builder
    {
        $query = StaffMessage::query();
        if ($answered !== null) {
            $query->where('answered', $answered);
        }
        if (! Permission::can(PermissionEnum::STAFF_MEMBER, User::findOrFail((int) $uid))) {
            // Not staff member only can see authorized
            $permissions = app(ToolRepository::class)->listUserAllPermissions($uid);
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
    public function updateStaffMessageCountCache($uid = 0, $type = '', $value = '')
    {
        if ($uid === false) {
            Cache::forgetWithLocales(self::STAFF_MESSAGE_NEW_CACHE_KEY);
            Cache::forgetWithLocales(self::STAFF_MESSAGE_TOTAL_CACHE_KEY);
        } else {
            $redis = Redis::connection()->client();
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
    public function getStaffMessageCountCache($uid = 0, $type = '')
    {
        $redis = Redis::connection()->client();

        return match ($type) {
            'total' => $redis->hGet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, (string) $uid),
            'new' => $redis->hGet(self::STAFF_MESSAGE_NEW_CACHE_KEY, (string) $uid),
            default => throw new \InvalidArgumentException("Invalid type: $type")
        };
    }
}
