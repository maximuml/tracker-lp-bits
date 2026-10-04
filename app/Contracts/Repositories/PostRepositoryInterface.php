<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

interface PostRepositoryInterface
{
    public function getTotalPostsCount(): int;

    public function getTodayPostsCount(string $todayDate): int;

    public function getLastPostId(): ?int;

    public function updateLastCatchup(int $userId, int $lastPostId): bool;

    public function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool;

    public function createPost(int $topicId, int $userId, string $body, string $date): int;

    public function countTopicPosts(int $topicid, ?int $authorId = null): int;

    /**
     * @return array<int>
     */
    public function getTopicPostIds(int $topicid, ?int $authorId = null): array;

    /** @return Collection<int, Post> */
    public function getTopicPosts(int $topicid, ?int $authorId, int $offset, int $perPage): Collection;

    public function countUserPosts(int $userId): int;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int> map of user id to post count
     */
    public function countUserPostsBatch(array $userIds): array;

    /**
     * @param  array<int, int>  $topicIds
     * @return array<int, int> map of topic id to post count
     */
    public function countTopicPostsBatch(array $topicIds): array;

    public function updateUserLastPost(int $userId, string $date): bool;

    public function deletePost(int $postid, int $topicid, int $forumid): bool;

    public function countForumSearchPosts(string $keywords, int $minClass): int;

    /**
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): \Illuminate\Support\Collection;

    public function getForumTodayPostCount(int $forumid, string $todayDate): int;

    public function findLastIdAddedBefore(string $before): ?int;
}
