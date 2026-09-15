<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

/**
 * Offer comments: the comments table rows linked via the offer column.
 */
final class OfferCommentRepository extends BaseRepository
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
