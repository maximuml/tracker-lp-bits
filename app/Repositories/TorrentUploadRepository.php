<?php

namespace App\Repositories;

use App\Models\Torrent;
use App\Models\User;
use Nexus\Database\NexusDB;

class TorrentUploadRepository
{
    /** @param  int  $catId */
    public static function getCategoryMode(int $catId): ?string
    {
        return \App\Models\Category::query()->where('id', $catId)->value('mode');
    }

    /** @param  int  $userId */
    public static function allowedOfferCount(int $userId): int
    {
        return NexusDB::table('offers')
            ->where('allowed', 'allowed')
            ->where('userid', $userId)
            ->count();
    }

    /**
     * @param  int  $offerId
     * @param  int  $userId
     */
    public static function isAllowedOffer(int $offerId, int $userId): bool
    {
        return NexusDB::table('offers')
            ->where('id', $offerId)
            ->where('allowed', 'allowed')
            ->where('userid', $userId)
            ->exists();
    }

    /** @param  int  $torrentId */
    public static function rollbackTorrent(int $torrentId): void
    {
        Torrent::query()->where('id', $torrentId)->delete();
    }

    /**
     * @param  int  $torrentId
     * @param  array<int|string, mixed>  $fileList
     */
    public static function syncFiles(int $torrentId, array $fileList): void
    {
        NexusDB::table('files')->where('torrent', $torrentId)->delete();

        $inserts = [];
        foreach ($fileList as $file) {
            $inserts[] = [
                'torrent' => $torrentId,
                'filename' => $file['path'],
                'size' => $file['size'],
            ];
        }

        if (! empty($inserts)) {
            NexusDB::table('files')->insert($inserts);
        }
    }

    /**
     * @param  int  $offerId
     * @param  int  $uploaderId
     * @return  array<int|string, mixed>
     */
    public static function getOfferVoterIds(int $offerId, int $uploaderId): array
    {
        return NexusDB::table('offervotes')
            ->where('offerid', $offerId)
            ->where('userid', '!=', $uploaderId)
            ->where('vote', 'yeah')
            ->pluck('userid')
            ->all();
    }

    /**
     * @param  int  $offerId
     * @param  int  $uploaderId
     */
    public static function finalizeOffer(int $offerId, int $uploaderId): void
    {
        NexusDB::table('offers')->where('id', $offerId)->delete();
        NexusDB::table('offervotes')->where('offerid', $offerId)->delete();
        NexusDB::table('comments')->where('offer', $offerId)->delete();
        User::query()->where('id', $uploaderId)->increment('offer_allowed_count');
    }
}
