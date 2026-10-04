<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

interface UsercpLookupRepositoryInterface
{
    public function getCommentCount(int $userId): int;

    public function getForumPostCount(int $userId): int;

    public function getTotalPostCount(): int;

    public function getTopicPostCount(int $topicId): int;

    /**
     * @param  array<int, int>  $topicIds
     * @return array<int, int> map of topic id to post count
     */
    public function getTopicPostCounts(array $topicIds): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadTopics(int $userId, int $limit = 5): array;

    /**
     * @return array<int, \stdClass>
     */
    public function getCountryOptions(): array;

    /**
     * @return array<int, \stdClass>
     */
    public function getBitbucketOptions(): array;

    public function countBitbucket(): int;

    /**
     * @return list<\stdClass>
     */
    public function listBitbucket(int $offset, int $limit): array;

    public function countryExists(int $countryId): bool;
}
