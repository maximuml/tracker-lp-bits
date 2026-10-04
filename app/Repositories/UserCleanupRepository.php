<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserClass;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserBanLog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Housekeeping queries on the users / user_ban_logs tables: stale-account
 * sweeps, seed-bonus counter resets, catch-up backfill and class-change
 * selects for the cleanup tasks.
 */
final class UserCleanupRepository extends BaseRepository
{
    /**
     * Columns the inactive-user sweep may order its threshold by.
     */
    private const INACTIVE_DATE_COLUMNS = ['last_access', 'added'];

    public function resetSeedBonusCounters(string $cutoff): int
    {
        return User::query()
            ->where('seed_points_updated_at', '<', $cutoff)
            ->update([
                'seed_points_per_hour' => 0,
                'seed_bonus_per_hour' => 0,
                'seeding_torrent_count' => 0,
                'seeding_torrent_size' => 0,
            ]);
    }

    public function deleteStaleUnconfirmedAccounts(int $deadlineTs): int
    {
        return User::query()
            ->where('status', UserStatus::PENDING->value)
            ->whereRaw('added < FROM_UNIXTIME(?)', [$deadlineTs])
            ->whereRaw('last_login < FROM_UNIXTIME(?)', [$deadlineTs])
            ->whereRaw('last_access < FROM_UNIXTIME(?)', [$deadlineTs])
            ->delete();
    }

    public function countByClientselect(int $clientId): int
    {
        return User::query()->where('clientselect', $clientId)->count();
    }

    public function updateLastCatchupBelow(int $postId): int
    {
        return User::query()->where('last_catchup', '<', $postId)->update(['last_catchup' => $postId]);
    }

    /**
     * Confirmed enabled users below $maxClass whose $dateColumn is older than
     * $before. When $iniUpload is given, only accounts that never transferred
     * (downloaded = 0, uploaded in {0, $iniUpload}) match — the "no transfer"
     * sweep variants.
     *
     * @return EloquentCollection<int, User>
     */
    public function listInactiveUsers(
        bool $parked,
        string $dateColumn,
        string $before,
        int $maxClass,
        ?int $iniUpload = null,
    ): Collection {
        if (! in_array($dateColumn, self::INACTIVE_DATE_COLUMNS, true)) {
            throw new InvalidArgumentException("unsupported inactive-user date column: {$dateColumn}");
        }

        return User::query()
            ->where('parked', $parked ? 1 : 0)
            ->where('status', UserStatus::CONFIRMED->value)
            ->where('class', '<', $maxClass)
            ->where($dateColumn, '<', $before)
            ->where('enabled', true)
            ->when($iniUpload !== null, function ($query) use ($iniUpload): void {
                $query->where('downloaded', 0)
                    ->where(function ($q) use ($iniUpload): void {
                        $q->where('uploaded', 0)->orWhere('uploaded', $iniUpload);
                    });
            })
            ->get(['id', 'username', 'lang']);
    }

    /**
     * @param  callable(Collection<int, User>): void  $callback
     */
    public function chunkDisabledUsersBefore(string $before, int $chunkSize, callable $callback): void
    {
        User::query()
            ->where('enabled', false)
            ->where('last_access', '<', $before)
            ->select(['id', 'username', 'lang'])
            ->orderBy('id', 'asc')
            ->chunk($chunkSize, $callback);
    }

    /**
     * @param  array<int>  $ids
     */
    public function disableUsers(array $ids): int
    {
        return User::query()->whereIn('id', $ids)->update(['enabled' => false]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertBanLogs(array $rows): bool
    {
        return UserBanLog::query()->insert($rows);
    }

    /**
     * Users of $class whose uploaded < downloaded * $ratio (demotion rule).
     *
     * @return Collection<int, User>
     */
    public function listUserIdsByClassRatioBelow(int $class, float $ratio): Collection
    {
        return User::query()
            ->where('class', $class)
            ->whereRaw('uploaded < downloaded * ?', [$ratio])
            ->get(['id']);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function listExpiredLeechWarnedUsers(string $before, int $maxClass): Collection
    {
        return User::query()
            ->where('class', '<', $maxClass)
            ->where('donor', false)
            ->where('enabled', true)
            ->where('leechwarn', true)
            ->where('leechwarnuntil', '<', $before)
            ->get(['id', 'username', 'lang']);
    }

    /**
     * Peasants meeting the promotion ratio rule (uploaded/downloaded >= ratio)
     * inside a downloaded window.
     *
     * @return Collection<int, User>
     */
    public function listPeasantIdsWithHighRatio(int $downloadedFloor, ?int $downloadedRoof, float $minRatio): Collection
    {
        return User::query()
            ->where('class', UserClass::PEASANT->value)
            ->where('downloaded', '>=', $downloadedFloor)
            ->when($downloadedRoof !== null && $downloadedRoof > $downloadedFloor, function ($query) use ($downloadedRoof): void {
                $query->where('downloaded', '<', $downloadedRoof);
            })
            ->whereRaw('uploaded / downloaded >= ?', [$minRatio])
            ->get(['id']);
    }

    /**
     * Users of $class over the downloaded floor whose uploaded/downloaded
     * ratio is below $maxRatio (demotion rule).
     *
     * @return Collection<int, User>
     */
    public function listUserIdsWithLowRatio(int $class, int $downloadedFloor, float $maxRatio): Collection
    {
        return User::query()
            ->where('class', $class)
            ->where('downloaded', '>', $downloadedFloor)
            ->whereRaw('uploaded / downloaded < ?', [$maxRatio])
            ->get(['id']);
    }

    /**
     * Class-promotion candidates: peers at $class with enough downloaded,
     * seed points, ratio and account age.
     *
     * @return Collection<int, User>
     */
    public function listPromotionCandidates(
        int $class,
        int $downloadedLimit,
        int|float $minSeedPoints,
        float $minRatio,
        string $addedBefore,
    ): Collection {
        return User::query()
            ->where('class', (string) $class)
            ->where('downloaded', '>=', $downloadedLimit)
            ->where('seed_points', '>=', $minSeedPoints)
            ->whereRaw('uploaded / downloaded >= ?', [$minRatio])
            ->where('added', '<', $addedBefore)
            ->get(['id', 'max_class_once']);
    }

    /**
     * @param  array<int>  $ids
     * @param  array<string, mixed>  $attributes
     */
    public function updateWhereInIds(array $ids, array $attributes): int
    {
        return User::query()->whereIn('id', $ids)->update($attributes);
    }

    /**
     * Enabled, non-donor users below VIP class eligible for a H&R ban,
     * hydrated with their language row for localized ban messages.
     *
     * @param  array<int, int|string>  $userIds
     * @return EloquentCollection<int, User>
     */
    public function listBanCandidates(array $userIds): EloquentCollection
    {
        return User::query()
            ->with('language')
            ->where('class', '<', UserClass::VIP->value)
            ->where('enabled', true)
            ->where('donor', false)
            ->find($userIds, ['id', 'username', 'lang']);
    }

    /**
     * Enabled users whose warning has expired — RemoveUserWarning sweep.
     *
     * @return EloquentCollection<int, User>
     */
    public function listExpiredWarnings(): EloquentCollection
    {
        return User::query()
            ->with('language')
            ->where('enabled', true)
            ->where('warned', true)
            ->where('warneduntil', '<', now())
            ->get();
    }

    /**
     * Users whose VIP status has expired — RemoveUserVipStatus sweep.
     *
     * @return EloquentCollection<int, User>
     */
    public function listExpiredVips(): EloquentCollection
    {
        return User::query()
            ->with('language')
            ->where('vip_added', true)
            ->where('vip_until', '<', now())
            ->get();
    }

    /**
     * Users whose donor status has expired — RemoveUserDonorStatus sweep.
     *
     * @return EloquentCollection<int, User>
     */
    public function listExpiredDonors(): EloquentCollection
    {
        return User::query()
            ->with('language')
            ->where('donor', true)
            ->whereNotNull('donoruntil')
            ->where('donoruntil', '<', now())
            ->get();
    }
}
