<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SnatchFinished;
use App\Models\Snatch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * snatches table — per-user per-torrent transfer records.
 */
final class SnatchRepository extends BaseRepository
{
    /** @param  array<string, mixed>  $fields */
    public function updateById(int $id, array $fields): int
    {
        return Snatch::query()->where('id', $id)->update($fields);
    }

    /** @param  array<string, mixed>  $attributes */
    public function insert(array $attributes): bool
    {
        return Snatch::query()->insert($attributes);
    }

    public function findIdByUserTorrentIp(int $userId, int $torrentId, string $ip): ?int
    {
        /** @var int|string|null */
        $id = Snatch::query()
            ->where('userid', $userId)
            ->where('torrentid', $torrentId)
            ->where('ip', $ip)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function linkHitAndRun(int $snatchId, int $hitAndRunId): int
    {
        return Snatch::query()->where('id', $snatchId)->update(['hit_and_run_id' => $hitAndRunId]);
    }

    /**
     * Finished records of a torrent with their owner user, paginated
     * (torrent-details snatch list).
     *
     * @return LengthAwarePaginator<int, Snatch>
     */
    public function paginateFinishedForTorrent(int $torrentId): LengthAwarePaginator
    {
        return Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('finished', SnatchFinished::YES->value)
            ->with('user')
            ->orderByDesc('completedat')
            ->paginate();
    }

    /** Row lock for update inside the caller's announce transaction. */
    public function lockById(int $id): ?\stdClass
    {
        /** @var \stdClass|null */
        return Snatch::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->toBase()
            ->first();
    }

    /**
     * Seedtime/leechtime sums grouped by user for the batch refresh job.
     *
     * @param  array<int, int>  $userIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Snatch>
     */
    public function sumTimeByUser(array $userIds): \Illuminate\Database\Eloquent\Collection
    {
        return Snatch::query()
            ->selectRaw('userid, sum(seedtime) as seedtime_sum, sum(leechtime) as leechtime_sum')
            ->whereIn('userid', $userIds)
            ->groupBy('userid')
            ->get();
    }

    /**
     * Download-progress rows (`to_go` + torrent size) for one user.
     *
     * @param  array<int, int>  $torrentIds
     * @return Collection<int, array{to_go: mixed, torrentid: int, size: mixed}>
     */
    public function listToGoWithSize(int $uid, array $torrentIds): Collection
    {
        /** @var Collection<int, array{to_go: mixed, torrentid: int, size: mixed}> */
        return Snatch::query()
            ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
            ->select('snatched.to_go', 'snatched.torrentid', 'torrents.size')
            ->where('snatched.userid', $uid)
            ->whereIn('snatched.torrentid', $torrentIds)
            ->toBase()
            ->get()
            ->map(fn ($row) => (array) $row);
    }
}
