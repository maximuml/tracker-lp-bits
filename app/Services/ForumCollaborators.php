<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Repositories\PostLookupRepository;
use App\Repositories\TopicRepository;

/**
 * Parameter object bundling the shared collaborators of the forum
 * services — data-access repositories plus the bonus ledger used by
 * topic/post accruals and moderation clawbacks — keeps their
 * constructors within the RepositorySizeTest dependency cap.
 */
final readonly class ForumCollaborators
{
    public function __construct(
        public ForumRepositoryInterface $forums,
        public TopicRepository $topics,
        public PostRepositoryInterface $posts,
        public PostLookupRepository $postLookup,
        public BonusLedgerService $ledger,
        public ForumLookupService $lookup,
    ) {}
}
