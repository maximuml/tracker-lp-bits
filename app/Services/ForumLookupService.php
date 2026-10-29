<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForumModRepository;
use App\Support\Cache\NexusCache;
use App\Support\Locale;
use App\Support\State\ForumState;
use App\Support\UserDisplay;

/**
 * Cached forum data lookups drained out of `Support\Forum` —
 * backs `get_forum_moderators` and `get_forum_pic_folder`.
 */
final class ForumLookupService
{
    public function __construct(
        private readonly ForumModRepository $forumModRepository,
        private readonly NexusCache $cache,
    ) {}

    /**
     * Return the relative forum-picture folder path.
     *
     * Mirrors `get_forum_pic_folder()`.
     */
    public function picFolder(?string $langFolder = null): string
    {
        return 'pic/forum_pic/'.($langFolder ?? Locale::currentLangDir());
    }

    /**
     * Return a comma-separated list of moderators for the given forum.
     *
     * Mirrors `get_forum_moderators()`.
     */
    public function moderators(int $forumId, bool $plainText = true): string
    {
        if (ForumState::instance()->moderatorsArray === null) {
            $cached = $this->cache->get('forum_moderator_array');
            if ($cached !== false && is_array($cached)) {
                ForumState::instance()->moderatorsArray = $cached;
            } else {
                ForumState::instance()->moderatorsArray = $this->forumModRepository->getModeratorArray();
                $this->cache->put('forum_moderator_array', ForumState::instance()->moderatorsArray, 86200);
            }
        }

        $userIds = ForumState::instance()->moderatorsArray[$forumId] ?? [];
        UserDisplay::preload($userIds);
        $names = [];
        foreach ($userIds as $userId) {
            $names[] = $plainText ? UserDisplay::plainUsername($userId) : UserDisplay::username($userId);
        }

        return rtrim(implode(', ', $names), ', ');
    }
}
