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
     * @param  Builder  $query
     * @param  array<string, mixed>|object|null  $user
     */
    public function applyTypeFilter($query, string $type, $user = null): void;

    /**
     * @param  array<string, mixed>|null  $user
     */
    public function maxId(string $type, ?array $user): int;

    /**
     * @param  array<string, mixed>|null  $user
     * @return Collection<int, \stdClass>
     */
    public function listLatest(string $type, ?array $user, int $limit): Collection;

    /**
     * @param  array<string, mixed>|null  $user
     */
    public function newAfterIdQuery(string $type, int $lastId, ?array $user): Builder;
}
