<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Post;
use App\Models\User;
use App\Support\Forum;

/**
 * W1-04: Authorization policy for post mutations.
 * Extracts ownership/moderation checks that were previously inline in ForumService.
 */
class PostPolicy extends BasePolicy
{
    /**
     * Whether the user can edit a post.
     * The post owner, forum moderators, and staff with POST_MANAGE can edit.
     */
    public function update(User $user, Post $post): bool
    {
        if ((int) $post->userid === (int) $user->id) {
            return true;
        }

        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($post->id, 'post');
    }

    /**
     * Whether the user can delete a post.
     * Forum moderators and staff with POST_MANAGE can delete.
     */
    public function delete(User $user, Post $post): bool
    {
        if (Permission::can(PermissionEnum::POST_MANAGE, $user)) {
            return true;
        }

        return Forum::isModerator($post->id, 'post');
    }
}
