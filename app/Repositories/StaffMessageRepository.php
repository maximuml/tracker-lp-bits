<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Events\StaffMessageCreated;
use App\Models\StaffMessage;
use App\Services\PermissionChecker;
use App\Support\Cache;
use App\Support\RedisGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redis;

/**
 * Staff message repository: staff message counts and cache management.
 */
class StaffMessageRepository extends BaseRepository
{
    public function __construct(
        private readonly ToolRepositoryInterface $toolRepository,
        private readonly PermissionChecker $permissionChecker,
    ) {}

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
        if (! $this->permissionChecker->userCan(PermissionEnum::STAFF_MEMBER->value, false, (int) $uid)) {
            // Not staff member only can see authorized
            $permissions = $this->toolRepository->listUserAllPermissions($uid);
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
            RedisGuard::attempt(static function () use ($uid, $type, $value) {
                $redis = Redis::connection()->client();
                match ($type) {
                    'total' => $redis->hSet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, $uid, $value),
                    'new' => $redis->hSet(self::STAFF_MESSAGE_NEW_CACHE_KEY, $uid, $value),
                    default => throw new \InvalidArgumentException("Invalid type: $type")
                };
            });
        }
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $type
     * @return mixed
     */
    public function getStaffMessageCountCache($uid = 0, $type = '')
    {
        return RedisGuard::attempt(
            static function () use ($uid, $type) {
                $redis = Redis::connection()->client();

                return match ($type) {
                    'total' => $redis->hGet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, (string) $uid),
                    'new' => $redis->hGet(self::STAFF_MESSAGE_NEW_CACHE_KEY, (string) $uid),
                    default => throw new \InvalidArgumentException("Invalid type: $type")
                };
            },
            false
        );
    }

    /**
     * Create a staff message and fire the event. Mirrors the former
     * `StaffMessage::add()` static helper.
     */
    public function add(int $sender, string $subject, string $msg): StaffMessage
    {
        $record = StaffMessage::query()->create([
            'sender' => $sender,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ]);
        event(new StaffMessageCreated($record));

        return $record;
    }
}
