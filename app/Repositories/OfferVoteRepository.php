<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\OfferVote;
use App\Models\Offer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Offer votes: the offervotes table plus the yeah/against counters on offers.
 */
final class OfferVoteRepository extends BaseRepository
{
    /**
     * @return array{yeah: int, against: int}
     */
    public function getVoteCounts(int $offerId): array
    {
        return [
            'yeah' => (int) DB::table('offervotes')->where('vote', OfferVote::YEAH->value)->where('offerid', $offerId)->count(),
            'against' => (int) DB::table('offervotes')->where('vote', OfferVote::AGAINST->value)->where('offerid', $offerId)->count(),
        ];
    }

    public function getVoteCount(int $offerId): int
    {
        return (int) DB::table('offervotes')->where('offerid', $offerId)->count();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function getVoteRows(int $offerId, int $offset, int $perPage): Collection
    {
        return DB::table('offervotes')
            ->where('offerid', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get();
    }

    public function userVoted(int $offerId, int $userId): bool
    {
        return (bool) DB::table('offervotes')->where('offerid', $offerId)->where('userid', $userId)->exists();
    }

    public function recordVote(int $offerId, int $userId, string $vote): void
    {
        DB::table('offervotes')->insert([
            'offerid' => $offerId,
            'userid' => $userId,
            'vote' => OfferVote::fromStringSafe($vote)->value,
        ]);
    }

    public function incrementVote(int $offerId, string $column): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->increment($column);
    }

    public function deleteOfferVotes(int $offerId): int
    {
        return DB::table('offervotes')->where('offerid', $offerId)->delete();
    }
}
