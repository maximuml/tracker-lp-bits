<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Offer comments: the comments table rows linked via the offer column.
 */
final class OfferCommentRepository extends BaseRepository implements OfferCommentRepositoryInterface
{
    public function deleteOfferComments(int $offerId): int
    {
        return Comment::query()->where('offer', $offerId)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastComment(int $offerId): ?array
    {
        $row = Comment::query()->where('offer', $offerId)->orderByDesc('added')->first(['user', 'added', 'text']);

        return $row ? $row->toArray() : null;
    }

    /**
     * Latest comment per offer, keyed by offer id.
     *
     * @param  array<int, int>  $offerIds
     * @return array<int, array<string, mixed>>
     */
    public function getLastComments(array $offerIds): array
    {
        $offerIds = array_values(array_unique(array_filter(array_map('intval', $offerIds))));
        if ($offerIds === []) {
            return [];
        }

        $rows = Comment::query()
            ->whereIn('id', function ($q) use ($offerIds) {
                $q->selectRaw('MAX(id)')->from('comments')->whereIn('offer', $offerIds)->groupBy('offer');
            })
            ->get(['id', 'offer', 'user', 'added', 'text']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->offer] = $row->toArray();
        }

        return $map;
    }

    public function countComments(int $offerId): int
    {
        return (int) Comment::query()->where('offer', $offerId)->count();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(int $offerId, int $offset, int $perPage): Collection
    {
        return Comment::query()
            ->where('offer', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate']);
    }
}
