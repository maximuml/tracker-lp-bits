<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Forum;
use App\Models\User;
use Illuminate\Support\Collection;

interface ForumRepositoryInterface
{
    /**
     * @return void
     */
    public function deleteForum(int $id);

    /**
     * @param  array<string, mixed>  $data
     * @return void
     */
    public function updateForum(int $id, array $data);

    /** @return array<string, mixed>|null */
    public function getForumRow(int $id): ?array;

    /**
     * @return void
     */
    public function clearForumCache();

    public function getActiveForumUserCount(): int;

    public function forumExists(int $id): bool;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForumsList(): array;

    public function getForumName(int $id): ?string;

    public function incrementForumTopicCount(int $forumid): bool;

    public function incrementForumPostCount(int $forumid, int $amount = 1): bool;

    public function getForumOrFail(int $id): Forum;

    public function getForumMinclasswrite(int $forumid): ?int;

    public function updateUserForumAccess(int $userId, string $date): bool;

    /**
     * @param  array<int>  $ids
     * @param  list<string>  $columns
     * @return Collection<int, User>
     */
    public function getUsersByIds(array $ids, array $columns): Collection;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Forum>
     */
    public function listOrdered(): \Illuminate\Database\Eloquent\Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Forum;

    /**
     * @return Collection<int, int>
     */
    public function listIds(): Collection;

    public function updateCounts(int $forumId, int $postcount, int $topiccount): int;
}
