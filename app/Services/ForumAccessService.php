<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Repositories\ForumModRepository;
use App\Repositories\TopicRepository;

/**
 * Forum authorization lookups drained out of `Support\Forum` —
 * backs `is_forum_moderator()` for policies, controllers and page
 * services.
 */
final class ForumAccessService
{
    public function __construct(
        private readonly ForumModRepository $forumModRepository,
        private readonly TopicRepository $topicRepository,
    ) {}

    /**
     * Check whether the current user is a moderator for the given
     * post / topic / forum.
     *
     * Mirrors `is_forum_moderator()`.
     */
    public function isModerator(int $id, string $in = 'post'): bool
    {
        $userId = ActorContext::current()->id;

        switch ($in) {
            case 'post':
                $topicId = $this->topicRepository->getTopicIdByPost($id);
                if ($topicId !== null) {
                    return $this->isModerator($topicId, 'topic');
                }

                return false;

            case 'topic':
                return $this->topicRepository->isModeratorOfTopic($id, $userId);

            case 'forum':
                return $this->forumModRepository->isModeratorOfForum($id, $userId);

            default:
                return false;
        }
    }
}
