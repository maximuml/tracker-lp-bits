<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Repositories\BookmarkRepository;
use App\Repositories\TorrentDetailRepository;
use App\Support\Bonus;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;

/**
 * Handles torrent bookmark toggle and the legacy "thanks" page action.
 *
 * Bookmark toggle is a simple add/remove with cache invalidation.
 * The legacy thanks page adds a thanks record and grants bonus points
 * to both the thanker and the torrent owner.
 */
final class TorrentBookmarkService
{
    public function __construct(
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly TorrentDetailRepository $torrentDetailRepository,
        private readonly ?NexusCache $cache = null,
    ) {}

    /**
     * Toggle a bookmark for a user on a torrent.
     *
     * @return string 'added', 'deleted', or 'failed' if torrent doesn't exist.
     */
    public function toggleBookmark(int $userId, int $torrentId): string
    {
        // Verify the torrent exists before adding a bookmark — prevents
        // orphaned bookmark records for non-existent torrents.
        if (! $this->torrentRepository->existsById($torrentId)) {
            return 'failed';
        }

        $bookmark = $this->bookmarkRepository->findByUserAndTorrent($userId, $torrentId);

        if ($bookmark) {
            $this->bookmarkRepository->deleteById((int) $bookmark->id);
            $status = 'deleted';
        } else {
            $this->bookmarkRepository->insertForUser($userId, $torrentId);
            $status = 'added';
        }

        if ($this->cache !== null) {
            $this->cache->forget('user_'.$userId.'_bookmark_array');
        }

        return $status;
    }

    /**
     * Record a thanks on a torrent and grant bonus points.
     *
     * @param  array<string, mixed>|null  $currentUser
     * @return array{torrentid: int, owner: int}
     *
     * @throws \RuntimeException If the torrent does not exist or the user already thanked.
     */
    public function thankTorrent(?array $currentUser, int $torrentId): array
    {
        $userId = (int) ($currentUser['id'] ?? 0);

        $torrentOwner = (int) $this->torrentRepository->getOwnerId($torrentId);
        if ($torrentOwner === 0) {
            throw new \RuntimeException('Invalid torrent id!');
        }

        if ($this->torrentDetailRepository->hasThanksRecord($torrentId, $userId)) {
            throw new \RuntimeException('You already said thanks!');
        }

        $this->torrentDetailRepository->insertThanks($torrentId, $userId);

        $bonusConfig = SiteConfig::current()->bonus;
        $saythanksBonus = $bonusConfig->sayThanks();
        $receivethanksBonus = $bonusConfig->receiveThanks();
        Bonus::updatePoints('+', $saythanksBonus, $userId);
        Bonus::updatePoints('+', $receivethanksBonus, $torrentOwner);

        return ['torrentid' => $torrentId, 'owner' => $torrentOwner];
    }
}
