<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * W1-05: Authorization policy for user control panel mutations.
 * Users can only update their own settings.
 */
class UsercpPolicy extends BasePolicy
{
    /**
     * Whether the authenticated user can update personal settings for $target.
     */
    public function updatePersonal(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    /**
     * Whether the authenticated user can update forum settings for $target.
     */
    public function updateForum(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    /**
     * Whether the authenticated user can update tracker settings for $target.
     */
    public function updateTracker(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }

    /**
     * Whether the authenticated user can update security settings for $target.
     */
    public function updateSecurity(User $user, User $target): bool
    {
        return $user->id === $target->id;
    }
}
