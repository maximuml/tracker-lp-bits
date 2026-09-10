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

    public function postExists(int $id): ?int;

    public function getPostForQuote(int $id): ?array;

    public function getPostForEdit(int $id): ?array;

    public function getPostWithTopic(int $postid): ?array;

    public function getPostEditInfo(int $postid): ?array;

    public function getPost(int $id): ?Post;

    public function getPostWithUser(int $id): ?Post;

    public function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool;

    public function getFirstPostId(int $topicid): int;

    public function createPost(int $topicId, int $userId, string $body, string $date): int;

    public function countTopicPosts(int $topicid, ?int $authorId = null): int;

    public function getTopicPostIds(int $topicid, ?int $authorId = null): array;

    public function getTopicPosts(int $topicid, ?int $authorId, int $offset, int $perPage): Collection;

    public function countUserPosts(int $userId): int;

    public function updateUserLastPost(int $userId, string $date): bool;

    public function getPostTopicAndUser(int $postid): ?array;

    public function getPreviousPostId(int $topicid, int $postid): ?int;

    public function deletePost(int $postid, int $topicid, int $forumid): bool;

    public function getPostArrayById(int $id): array;

    public function findPostArrayById(int $id): ?array;

    public function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): array;

    public function getForumTodayPostCount(int $forumid, string $todayDate): int;
}
