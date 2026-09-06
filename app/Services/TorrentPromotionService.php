<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TorrentModerationRepository;

/**
 * Torrent promotion and special-state management.
 *
 * Extracted from TorrentRepository to reduce god-object surface area.
 * Delegates to TorrentModerationRepository for the permission-gated
 * promotion, sticky, and hit-and-run state mutations.
 */
final class TorrentPromotionService
{
    public function __construct(
        private readonly TorrentModerationRepository $moderationRepository,
    ) {}

    /**
     * @param  mixed  $id
     * @param  mixed  $posState
     * @param  mixed  $posStateUntil
     */
    public function setPosState($id, $posState, $posStateUntil = null): int
    {
        return $this->moderationRepository->setPosState($id, $posState, $posStateUntil);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $hrStatus
     */
    public function setHr($id, $hrStatus): int
    {
        return $this->moderationRepository->setHr($id, $hrStatus);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $spState
     * @param  mixed  $promotionTimeType
     * @param  mixed  $promotionUntil
     */
    public function setSpState($id, $spState, $promotionTimeType, $promotionUntil = null): int
    {
        return $this->moderationRepository->setSpState($id, $spState, $promotionTimeType, $promotionUntil);
    }
}
