<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Peer;

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
}
