<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\SnatchFinished;
use App\Models\Snatch;
use Illuminate\Pagination\LengthAwarePaginator;

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
}
