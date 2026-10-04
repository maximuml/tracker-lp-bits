<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * H&R row access on the announce + cron paths — CAS status updates, the
 * INSPECTING scan query, per-user UNREACHED counts, and the announce-path
 * pair lookup/insert. Kept separate from HitAndRunRepository (UI CRUD
 * surface) because that repo depends on HitAndRunCronjobService.
 */
final class HitAndRunLookupRepository
{
    /**
     * CAS transition out of INSPECTING — 0 when another worker already
     * moved the row.
     *
     * @param  array<string, mixed>  $update
     */
    public function updateInspectingTo(int $id, array $update): int
    {
        return HitAndRun::query()
            ->where('id', $id)
            ->where('status', HitAndRunStatus::INSPECTING->value)
            ->update($update);
    }

    /**
     * INSPECTING rows with torrent/snatch/user(+language) projections for
     * the cronjob sweep — builder returned so callers keep their own
     * where() filters and pagination.
     *
     * @return Builder<HitAndRun>
     */
    public function inspectingQuery(): Builder
    {
        return HitAndRun::query()
            ->where('status', HitAndRunStatus::INSPECTING->value)
            ->with([
                'torrent' => function ($query) {
                    $query->select(['id', 'size', 'name', 'category']);
                },
                'snatch',
                'user' => function ($query) {
                    $query->select(['id', 'username', 'lang', 'class', 'donoruntil', 'enabled', 'notifs']);
                },
                'user.language',
            ]);
    }

    /**
     * Per-user UNREACHED counts at or above the ban threshold, optionally
     * scoped to one basic_category mode.
     *
     * @return Collection<int, HitAndRun>
     */
    public function listUnreachedCountsAtLeast(int $minCounts, ?int $searchBoxId): Collection
    {
        $query = HitAndRun::query()
            ->selectRaw('count(*) as counts, uid')
            ->where('status', HitAndRunStatus::UNREACHED->value)
            ->groupBy('uid')
            ->havingRaw('count(*) >= ?', [$minCounts]);
        if ($searchBoxId !== null) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($searchBoxId) {
                return $query->where('mode', $searchBoxId);
            });
        }

        return $query->get();
    }

    /**
     * Announce H&R lookup — the same pair is re-fetched after
     * insertOrIgnore.
     */
    public function findByUidTorrent(int $userId, int $torrentId): ?HitAndRun
    {
        /** @var HitAndRun|null */
        return HitAndRun::query()
            ->where('uid', $userId)
            ->where('torrent_id', $torrentId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertOrIgnore(array $attributes): int
    {
        return HitAndRun::query()->insertOrIgnore($attributes);
    }
}
