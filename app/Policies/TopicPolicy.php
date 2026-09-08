<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Support\Forum;

/**
 * W1-04: Authorization policy for topic mutations.
 * Extracts ownership/moderation checks that were previously inline in ForumService.
 */
class TopicPolicy extends BasePolicy
{
    public function __construct(
        private readonly ForumRepository $repository,
    ) {}

    /**
     * Whether the user can create a topic in the given forum.
     */
    public function create(User $user, int $forumId): bool
    {
        $forumRow = $this->repository->getForumRow($forumId);
        if ($forumRow === null) {
            return false;
        }

        $class = (int) $user->class;
        if ($class < (int) ($forumRow['minclassread'] ?? 0)) {
            return false;
        }
        if ($class < (int) ($forumRow['minclasswrite'] ?? 0)) {
            return false;
        }
        if ($class < (int) ($forumRow['minclasscreate'] ?? 0)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the user can reply to a topic.
     */
    public function reply(User $user, Topic $topic): bool
    {
        $forumRow = $this->repository->getForumRow($topic->forumid);
        if ($forumRow === null) {
            return false;
        }

        $class = (int) $user->class;
        if ($class < (int) ($forumRow['minclassread'] ?? 0)) {
            return false;
        }
        if ($class < (int) ($forumRow['minclasswrite'] ?? 0)) {
            return false;
        }

        if ($topic->locked) {
            return Permission::can(PermissionEnum::POST_MANAGE, $user)
                || Forum::isModerator($topic->id, 'topic');
        }

        return true;
    }

    /**
     * Whether the user can move a topic to another forum.
     */
    public function move(User $user, Topic $topic): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($topic->id, 'topic');
    }

    /**
     * Whether the user can delete a topic.
     */
    public function delete(User $user, Topic $topic): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($topic->id, 'topic');
    }

    /**
     * Whether the user can lock/unlock a topic.
     */
    public function lock(User $user, Topic $topic): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($topic->id, 'topic');
    }

    /**
     * Whether the user can sticky/unsticky a topic.
     */
    public function sticky(User $user, Topic $topic): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($topic->id, 'topic');
    }

    /**
     * Whether the user can highlight a topic.
     */
    public function highlight(User $user, Topic $topic): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($topic->id, 'topic');
    }
}
