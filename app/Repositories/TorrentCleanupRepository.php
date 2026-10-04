<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use Illuminate\Support\Collection;

/**
 * Housekeeping queries on the torrents table: visibility/sticky/promotion
 * expiry and dead-torrent selection for the cleanup tasks.
 */
final class TorrentCleanupRepository extends BaseRepository
{
    public function markDeadVisibleTorrentsInvisible(string $lastActionBefore): int
    {
        return Torrent::query()
            ->where('visible', 1)
            ->where('last_action', '<', $lastActionBefore)
            ->where('seeders', 0)
            ->update(['visible' => 0]);
    }

    /**
     * @param  array<int, int|string>  $fromStates
     */
    public function expireStickyPositions(array $fromStates, int|string $toState): int
    {
        return Torrent::query()
            ->whereIn('pos_state', $fromStates)
            ->whereNotNull('pos_state_until')
            ->where('pos_state_until', '<', now())
            ->update([
                'pos_state' => $toState,
                'pos_state_until' => null,
            ]);
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function getExpiredGlobalPromotions(string $addedBefore, int $spState, int $promotionTimeType): Collection
    {
        return Torrent::query()
            ->where('added', '<', $addedBefore)
            ->where('sp_state', $spState)
            ->where('promotion_time_type', $promotionTimeType)
            ->toBase()
            ->get(['id', 'name']);
    }

    /**
     * @param  array<int>  $ids
     */
    public function setPromotionStateForIds(array $ids, int $state): int
    {
        return Torrent::query()
            ->whereIn('id', $ids)
            ->update(['sp_state' => $state]);
    }

    /**
     * @return Collection<int, Torrent>
     */
    public function listExpiredDeadlinePromotions(int $promotionTimeType): Collection
    {
        return Torrent::query()
            ->where('promotion_time_type', $promotionTimeType)
            ->where('promotion_until', '<', now())
            ->get(['id']);
    }

    /**
     * @param  array<int>  $ids
     */
    public function resetDeadlinePromotions(array $ids, int $spState, int $promotionTimeType): int
    {
        return Torrent::query()
            ->whereIn('id', $ids)
            ->update([
                'sp_state' => $spState,
                'promotion_time_type' => $promotionTimeType,
                'promotion_until' => null,
            ]);
    }

    /**
     * Dead torrents invisible long enough to delete, joined to their owners.
     *
     * @return Collection<int, \stdClass>
     */
    public function listDeadTorrentsForDeletion(string $lastActionBefore): Collection
    {
        return Torrent::query()
            ->from('torrents as t')
            ->leftJoin('users as u', 't.owner', '=', 'u.id')
            ->where('t.visible', 0)
            ->where('t.last_action', '<', $lastActionBefore)
            ->where('t.seeders', 0)
            ->where('t.leechers', 0)
            ->select('t.id', 't.name', 't.owner', 'u.id as uid')
            ->toBase()
            ->get();
    }
}
