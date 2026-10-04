<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OfferAllowed;
use App\Models\File;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TorrentUploadRepository
{
    public function isAllowedOffer(int $offerId, int $userId): bool
    {
        return DB::table('offers')
            ->where('id', $offerId)
            ->where('allowed', OfferAllowed::ALLOWED->value)
            ->where('userid', $userId)
            ->exists();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getOfferVoterIds(int $offerId, int $uploaderId): array
    {
        return DB::table('offervotes')
            ->where('offerid', $offerId)
            ->where('userid', '!=', $uploaderId)
            ->where('vote', 'yeah')
            ->pluck('userid')
            ->all();
    }

    public function finalizeOffer(int $offerId, int $uploaderId): void
    {
        DB::table('offers')->where('id', $offerId)->delete();
        DB::table('offervotes')->where('offerid', $offerId)->delete();
        DB::table('comments')->where('offer', $offerId)->delete();
        User::query()->where('id', $uploaderId)->increment('offer_allowed_count');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTorrent(array $attributes): Torrent
    {
        /** @var Torrent */
        return Torrent::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertExtra(array $attributes): bool
    {
        return TorrentExtra::query()->insert($attributes);
    }

    /**
     * @param  array<int, array<string, mixed>>|array<string, mixed>  $rows
     */
    public function insertFiles(array $rows): bool
    {
        return File::query()->insert($rows);
    }
}
