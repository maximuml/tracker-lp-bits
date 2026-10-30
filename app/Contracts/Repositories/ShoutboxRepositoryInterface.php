<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ShoutboxRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function history(Request $request): array;

    /**
     * @param  list<int>  $shoutIds
     * @return array{counts: array<int, array<string, int>>, mine: array<int, list<string>>, users: array<int, array<string, list<string>>>}
     */
    public function prefetchReactions(array $shoutIds, int $currentUserId): array;

    /**
     * @return array<string, int>
     */
    public function getReactionCounts(int $shoutId): array;

    /**
     * @return list<string>
     */
    public function getMyReactions(int $shoutId, int $currentUserId): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMentions(int $userId, int $lastShoutId, int $limit = 50, bool $oldestFirst = true): array;

    public function countMentions(int $userId, int $lastShoutId): int;

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByUsername(string $username): ?array;

    public function torrentExists(int $id): bool;

    /**
     * Restrict a shoutbox query to the SB row type — the only type the
     * `shoutbox` table carries.
     *
     * @param  Builder  $query
     */
    public function applyTypeFilter($query): void;

    public function maxId(): int;

    /**
     * @return Collection<int, \stdClass>
     */
    public function listLatest(int $limit): Collection;

    /**
     * Prepared query for shouts after $lastId — re-executed by the SSE loop.
     */
    public function newAfterIdQuery(int $lastId): Builder;

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertMessage(array $data): void;

    public function findById(int $id): ?\stdClass;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateById(int $id, array $data): void;

    public function deleteWithReactions(int $id): void;

    public function deleteAllWithReactions(): void;

    /**
     * Toggle a reaction: deletes the existing row or inserts a new one.
     *
     * @return bool True when the reaction was added, false when removed.
     */
    public function toggleReaction(int $shoutId, int $userId, string $reaction): bool;

    public function deleteBefore(int $unixTs): int;
}
