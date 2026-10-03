<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

interface OfferCommentRepositoryInterface
{
    public function deleteOfferComments(int $offerId): int;

    /**
     * @return array<string, mixed>|null
     */
    public function getLastComment(int $offerId): ?array;

    /**
     * Latest comment per offer, keyed by offer id.
     *
     * @param  array<int, int>  $offerIds
     * @return array<int, array<string, mixed>>
     */
    public function getLastComments(array $offerIds): array;

    public function countComments(int $offerId): int;

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(int $offerId, int $offset, int $perPage): Collection;
}
