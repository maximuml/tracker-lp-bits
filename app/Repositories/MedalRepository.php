<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Medal;
use App\Models\User;
use App\Models\UserMedal;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedalRepository extends BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return LengthAwarePaginator<int, Medal>
     */
    public function getList(array $params): LengthAwarePaginator
    {
        $query = Medal::query();
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        /** @var array<string, mixed> $data */
        $data = $params;

        return Medal::query()->create($data);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function update(array $params, int $id)
    {
        $medal = Medal::query()->findOrFail($id);
        /** @var array<string, mixed> $data */
        $data = $params;
        $medal->update($data);

        return $medal;
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id)
    {
        return Medal::query()->findOrFail($id);
    }

    /**
     * delete a medal, also will delete all user medal.
     */
    public function delete(int $id): bool
    {
        $medal = Medal::query()->findOrFail($id);
        DB::transaction(function () use ($medal) {
            do {
                $deleted = UserMedal::query()->where('medal_id', $medal->id)->limit(10000)->delete();
            } while ($deleted > 0);
            $medal->delete();
        });

        return true;
    }

    /**
     * @param  mixed  $duration
     * @return mixed
     */
    public function grantToUser(int $uid, int $medalId, $duration = null)
    {
        $user = User::query()->findOrFail($uid, User::$commonFields);
        $authUser = Auth::user();
        if (! $authUser instanceof User || $authUser->class <= $user->class) {
            throw new \LogicException('No permission!');
        }
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        Logger::writeWithContext(LegacyDb::lastQuery(false, 'json'));
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $this->userAttachMedal($user, $medal);
    }

    public function userAttachMedal(User $user, Medal $medal): void
    {
        $expireAt = null;
        $bonusAdditionExpireAt = null;
        if ($medal->duration > 0) {
            $expireAt = Carbon::now()->addDays((int) $medal->duration)->toDateTimeString();
        }
        if ($medal->bonus_addition_duration > 0) {
            $bonusAdditionExpireAt = Carbon::now()->addDays((int) $medal->bonus_addition_duration)->toDateTimeString();
        }
        $user->medals()->attach([
            $medal->id => [
                'expire_at' => $expireAt,
                'bonus_addition_expire_at' => $bonusAdditionExpireAt,
                'status' => UserMedal::STATUS_NOT_WEARING,
            ],
        ]);
        Cache::clearUser($user->id);
    }

    /**
     * @return mixed
     */
    public function toggleUserMedalStatus(int $id, int $userId)
    {
        $userMedal = UserMedal::query()->findOrFail($id);
        if ($userMedal->uid != $userId) {
            throw new \LogicException('no privilege');
        }
        $current = $userMedal->status;
        if ($current == UserMedal::STATUS_NOT_WEARING) {
            $maxWearAllow = SiteConfig::current()->system->maximumNumberOfMedalsCanBeWorn();
            $user = User::query()->findOrFail($userId, User::$commonFields);
            $wearCount = $user->wearing_medals()->count();
            if ($maxWearAllow && $wearCount >= $maxWearAllow) {
                throw new NexusException(Locale::trans('medal.max_allow_wearing', ['count' => $maxWearAllow], null));
            }
            $userMedal->status = UserMedal::STATUS_WEARING;
        } elseif ($current == UserMedal::STATUS_WEARING) {
            $userMedal->status = UserMedal::STATUS_NOT_WEARING;
        }
        $userMedal->save();
        Cache::clearUser($userId);

        return $userMedal;
    }

    /**
     * @param  array<int|string, mixed>  $userMedalData
     * @return mixed
     */
    public function saveUserMedal(int $userId, array $userMedalData)
    {
        $user = User::query()->findOrFail($userId);
        $validMedals = $user->valid_medals;
        if ($validMedals->isEmpty()) {
            return true;
        }
        $rows = [];
        $wearCount = 0;
        $nowStr = now()->toDateTimeString();
        foreach ($validMedals as $medal) {
            $id = (int) $medal->pivot->id;
            if (isset($userMedalData[$id]['status'])) {
                $status = UserMedal::STATUS_WEARING;
                $wearCount++;
            } else {
                $status = UserMedal::STATUS_NOT_WEARING;
            }
            $rows[] = [
                'id' => $id,
                'status' => $status,
                'priority' => (int) ($userMedalData[$id]['priority'] ?? 0),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
        }
        $maxWearAllow = SiteConfig::current()->system->maximumNumberOfMedalsCanBeWorn();
        if ($maxWearAllow && $wearCount > $maxWearAllow) {
            throw new NexusException(Locale::trans('medal.max_allow_wearing', ['count' => $maxWearAllow], null));
        }
        Cache::clearUser($userId);
        if (empty($rows)) {
            return 0;
        }

        return DB::table('user_medals')->upsert($rows, ['id'], ['status', 'priority', 'updated_at']);
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    public function increaseExpireAt(Collection $collection, string $field, int $duration): void
    {
        $this->checkExpireField($field);
        $idArr = $collection->pluck('id')->toArray();
        $result = DB::table('user_medals')
            ->whereIn('id', $idArr)
            ->whereNotNull($field)
            ->update([$field => DB::raw("`$field` + INTERVAL $duration DAY")]);
        Logger::writeWithContext(sprintf(
            "operator: %s, increase records: %s $field + $duration day, result: %s",
            UserDisplay::currentUsername(), implode(', ', $idArr), $result
        ));
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    public function updateExpireAt(Collection $collection, string $field, Carbon $expireAt): void
    {
        $this->checkExpireField($field);
        $idArr = $collection->pluck('id')->toArray();
        $result = DB::table('user_medals')
            ->whereIn('id', $idArr)
            ->update([$field => $expireAt]);
        Logger::writeWithContext(sprintf(
            "operator: %s, update records: %s $field $expireAt, result: %s",
            UserDisplay::currentUsername(), implode(', ', $idArr), $result
        ));
    }

    /**
     * @param  Collection<int, mixed>  $collection
     */
    public function cancelExpireAt(Collection $collection, string $field): void
    {
        $this->checkExpireField($field);
        $idArr = $collection->pluck('id')->toArray();
        $result = DB::table('user_medals')
            ->whereIn('id', $idArr)
            ->update([$field => DB::raw('null')]);
        Logger::writeWithContext(sprintf(
            "operator: %s, update records: %s $field null, result: %s",
            UserDisplay::currentUsername(), implode(', ', $idArr), $result
        ));
    }

    private function checkExpireField(string $field): void
    {
        if (! in_array($field, ['expire_at', 'bonus_addition_expire_at'])) {
            throw new \InvalidArgumentException("invalid field: $field");
        }
    }
}
