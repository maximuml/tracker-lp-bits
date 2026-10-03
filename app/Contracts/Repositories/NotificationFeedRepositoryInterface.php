<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface NotificationFeedRepositoryInterface
{
    /**
     * @return array<string, int> channel => last read id (missing channels => 0)
     */
    public function savedCursors(int $userId): array;

    public function hasCursors(int $userId): bool;

    /**
     * @return array<string, int>
     */
    public function channelMaxes(int $userId): array;

    /**
     * @return array<string, int>
     */
    public function sourceMaxima(int $userId): array;

    /**
     * @param  array<string, int>  $maxes
     */
    public function saveCursors(int $userId, array $maxes): void;

    /**
     * @param  array<string, int>  $cursors
     * @return array<string, int>
     */
    public function unreadCounts(int $userId, array $cursors, int $userClass = 0): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function newCommentsOnOwnTorrents(int $userId, int $lastId, int $limit = 20, bool $oldestFirst = true): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function newPostsInOwnTopics(int $userId, int $lastId, int $limit = 20, bool $oldestFirst = true, int $userClass = 0): array;
}
