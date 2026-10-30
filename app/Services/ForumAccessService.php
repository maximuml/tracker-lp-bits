<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Post;
use App\Models\Topic;
use App\Repositories\ForumModRepository;
use App\Repositories\PostLookupRepository;
use App\Repositories\SettingRepository;
use App\Repositories\TopicRepository;
use App\Support\Logger;
use App\Support\State\ForumState;
use App\Support\UserDisplay;
use Illuminate\Support\Facades\Cache;

/**
 * Forum authorization lookups drained out of `Support\Forum` —
 * backs `is_forum_moderator()` and `can_view_post()` for policies,
 * controllers and page services.
 */
final class ForumAccessService
{
    public function __construct(
        private readonly ForumModRepository $forumModRepository,
        private readonly TopicRepository $topicRepository,
        private readonly PostLookupRepository $postLookupRepository,
        private readonly SettingRepository $settingRepository,
        private readonly ForumState $forumState,
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

    /**
     * Check whether `$uid` may view the given post in a protected forum.
     *
     * Mirrors `can_view_post()`.
     *
     * @param  array<string, mixed>|Post|int  $post
     */
    public function canViewPost(int $uid, array|Post|int $post): bool
    {
        if (! is_array($post)) {
            $post = $post instanceof Post ? $post : $this->postLookupRepository->getPostOrFail($post);
        }

        $topicId = $post['topicid'];
        if (! isset($this->forumState->topics[$topicId])) {
            $this->forumState->topics[$topicId] = $this->topicRepository->getTopicById($topicId);
        }
        /** @var Topic $topicInfo */
        $topicInfo = $this->forumState->topics[$topicId];
        $forumId = $topicInfo->forumid;

        if ($this->forumState->protectedForumIds === null) {
            $settingRepository = $this->settingRepository;
            $protected = Cache::remember('setting_protected_forum', 600, function () use ($settingRepository) {
                return $settingRepository->getByName('misc.protected_forum') ?? false;
            });
            $this->forumState->protectedForumIds = $protected ? (preg_split('/[,\s]+/', $protected) ?: []) : [];
        }

        if ($this->forumState->forumMods === null) {
            $this->forumState->forumMods = $this->forumModRepository->getForumMods();
        }

        $isForumMod = isset($this->forumState->forumMods[$forumId]) && $this->forumState->forumMods[$forumId] == $uid;
        $log = sprintf(
            'uid: %s, class: %s, post: %s, forumId: %s, protectedForumIdArr: %s, forumMods: %s, isForumMod: %s',
            $uid,
            UserDisplay::currentClass(),
            $post['id'],
            $forumId,
            json_encode($this->forumState->protectedForumIds),
            json_encode($this->forumState->forumMods),
            $isForumMod
        );

        if (
            in_array($forumId, $this->forumState->protectedForumIds)
            && UserDisplay::currentClass() < UserClassEnum::ADMINISTRATOR->value
            && $uid != $post['userid']
            && $uid != $topicInfo->userid
            && ! $isForumMod
        ) {
            Logger::writeWithContext("$log, FALSE");

            return false;
        }

        Logger::writeWithContext("$log, TRUE");

        return true;
    }
}
