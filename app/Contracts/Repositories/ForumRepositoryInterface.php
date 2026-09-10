<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface ForumRepositoryInterface
{
    public function deleteForum(int $id);

    public function updateForum(int $id, array $data);

    public function createForum(array $data): int;

    public function replaceModerators(int $forumId, array $userIds, int $limit = 3);

    public function getOverforums(): array;

    public function getMaxForumSort(): int;

    public function getForumRow(int $id): ?array;

    public function getForumsWithOverforum(): array;

    public function deleteOverforum(int $id);

    public function updateOverforum(int $id, array $data);

    public function createOverforum(array $data);

    public function getMaxOverforumSort(): int;

    public function getOverforumRow(int $id): ?array;

    public function getAllOverforums(): array;

    public function getOverforumsList(): array;

    public function getModeratorArray(): array;

    public function clearForumCache();

    public function clearOverforumCache();

    public function clearModeratorCache();

    public function isModeratorOfForum(int $forumId, int $userId): bool;

    public function getActiveForumUserCount(): int;

    public function forumExists(int $id): bool;

    public function getForumsList(): array;

    public function getForumName(int $id): ?string;

    public function incrementForumTopicCount(int $forumid): bool;

    public function incrementForumPostCount(int $forumid, int $amount = 1): bool;

    public function getForumMinclasswrite(int $forumid): ?int;

    public function getForumMods(): array;

    public function updateUserForumAccess(int $userId, string $date): bool;

    public function getUsersByIds(array $ids, array $columns): Collection;
}
