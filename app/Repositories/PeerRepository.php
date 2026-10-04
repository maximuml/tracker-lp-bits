<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Peer;
use Illuminate\Database\Eloquent\Collection;

class PeerRepository
{
    public function deleteInactiveForUser(int $userId, string $lastAction): int
    {
        return Peer::query()
            ->where('last_action', '<', $lastAction)
            ->where('userid', $userId)
            ->delete();
    }

    public function countForTorrent(int $torrentId): int
    {
        return Peer::query()->where('torrent', $torrentId)->count();
    }

    public function deleteInactiveBefore(string $deadline): int
    {
        return Peer::query()->where('last_action', '<', $deadline)->delete();
    }

    /**
     * All peer rows of a torrent (deduped by peer_id) with owner and
     * relative-torrent rows — the torrent-details peer list.
     *
     * @return Collection<int, Peer>
     */
    public function listForTorrent(int|string $torrentId): Collection
    {
        return Peer::query()
            ->where('torrent', $torrentId)
            ->groupBy('peer_id')
            ->with(['user', 'relative_torrent'])
            ->get();
    }

    /** Row lock for update inside the caller's announce transaction. */
    public function lockById(int $id): ?\stdClass
    {
        /** @var \stdClass|null */
        return Peer::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->toBase()
            ->first();
    }
}
