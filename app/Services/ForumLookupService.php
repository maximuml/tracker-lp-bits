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
        private readonly ?NexusCache $cache,
        private readonly ForumState $forumState,
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
        if ($this->forumState->moderatorsArray === null) {
            $cached = $this->cache !== null ? $this->cache->get('forum_moderator_array') : false;
            if ($cached !== false && is_array($cached)) {
                $this->forumState->moderatorsArray = $cached;
            } else {
                $this->forumState->moderatorsArray = $this->forumModRepository->getModeratorArray();
                if ($this->cache !== null) {
                    $this->cache->put('forum_moderator_array', $this->forumState->moderatorsArray, 86200);
                }
            }
        }

        $userIds = $this->forumState->moderatorsArray[$forumId] ?? [];
        UserDisplay::preload($userIds);
        $names = [];
        foreach ($userIds as $userId) {
            $names[] = $plainText ? UserDisplay::plainUsername($userId) : UserDisplay::username($userId);
        }

        return rtrim(implode(', ', $names), ', ');
    }
}
