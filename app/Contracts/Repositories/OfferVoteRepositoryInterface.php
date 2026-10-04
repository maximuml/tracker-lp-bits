<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface OfferVoteRepositoryInterface
{
    /**
     * @return array{yeah: int, against: int}
     */
    public function getVoteCounts(int $offerId): array;

    public function getVoteCount(int $offerId): int;

    /**
     * @return Collection<int, \stdClass>
     */
    public function getVoteRows(int $offerId, int $offset, int $perPage): Collection;

    public function userVoted(int $offerId, int $userId): bool;

    public function recordVote(int $offerId, int $userId, string $vote): void;

    public function incrementVote(int $offerId, string $column): bool;

    public function deleteOfferVotes(int $offerId): int;

    /**
     * @param  list<int>  $offerIds
     */
    public function deleteVotesForOffers(array $offerIds): int;
}
