<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TorrentUploadRepository
{
    public static function getCategoryMode(int $catId): ?string
    {
        return Category::query()->where('id', $catId)->value('mode');
    }

    public static function allowedOfferCount(int $userId): int
    {
        return DB::table('offers')
            ->where('allowed', 'allowed')
            ->where('userid', $userId)
            ->count();
    }

    public static function isAllowedOffer(int $offerId, int $userId): bool
    {
        return DB::table('offers')
            ->where('id', $offerId)
            ->where('allowed', 'allowed')
            ->where('userid', $userId)
            ->exists();
    }

    public static function rollbackTorrent(int $torrentId): void
    {
        Torrent::query()->where('id', $torrentId)->delete();
    }

    /**
     * @param  array<int|string, mixed>  $fileList
     */
    public static function syncFiles(int $torrentId, array $fileList): void
    {
        DB::table('files')->where('torrent', $torrentId)->delete();

        $inserts = [];
        foreach ($fileList as $file) {
            $inserts[] = [
                'torrent' => $torrentId,
                'filename' => $file['path'],
                'size' => $file['size'],
            ];
        }

        if (! empty($inserts)) {
            DB::table('files')->insert($inserts);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function getOfferVoterIds(int $offerId, int $uploaderId): array
    {
        return DB::table('offervotes')
            ->where('offerid', $offerId)
            ->where('userid', '!=', $uploaderId)
            ->where('vote', 'yeah')
            ->pluck('userid')
            ->all();
    }

    public static function finalizeOffer(int $offerId, int $uploaderId): void
    {
        DB::table('offers')->where('id', $offerId)->delete();
        DB::table('offervotes')->where('offerid', $offerId)->delete();
        DB::table('comments')->where('offer', $offerId)->delete();
        User::query()->where('id', $uploaderId)->increment('offer_allowed_count');
    }
}
