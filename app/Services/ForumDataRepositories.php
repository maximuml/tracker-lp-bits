<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;

/**
 * Parameter object bundling the forum data-access dependencies of
 * ForumService — keeps its constructor within the RepositorySizeTest
 * dependency cap.
 */
final readonly class ForumDataRepositories
{
    public function __construct(
        public ForumRepositoryInterface $forums,
        public TopicRepository $topics,
        public PostRepositoryInterface $posts,
        public PostLookupRepository $postLookup,
    ) {}
}
