<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserClass as UserClassEnum;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\SettingRepository;
use App\Support\Cache\LegacyRedisCache;
use Illuminate\Support\Facades\Cache;

/**
 * Legacy forum helpers extracted from `include/functions.php`.
 *
 * Backs `get_forum_pic_folder`, `get_forum_moderators`,
 * `set_forum_moderators`, `is_forum_moderator` and `get_post_row`.
 */
final class Forum
{
    /**
     * Return the relative forum-picture folder path.
     *
     * Mirrors `get_forum_pic_folder()`.
     */
    public static function picFolder(string $langFolder): string
    {
        return 'pic/forum_pic/'.$langFolder;
    }

    /**
     * Context-aware wrapper for {@see picFolder()}.
     */
    public static function picFolderWithContext(): string
    {
        return self::picFolder((string) app(Globals::class)->get('CURLANGDIR', ''));
    }

    /**
     * Return a comma-separated list of moderators for the given forum.
     *
     * Mirrors `get_forum_moderators()`.
     */
    public static function moderators(?LegacyRedisCache $cache, int|string $forumId, bool $plainText = true): string
    {
        static $moderatorsArray = null;

        if ($moderatorsArray === null) {
            $cached = $cache !== null ? $cache->get_value('forum_moderator_array') : false;
            if ($cached !== false && is_array($cached)) {
                $moderatorsArray = $cached;
            } else {
                $moderatorsArray = app(ForumRepository::class)->getModeratorArray();
                if ($cache !== null) {
                    $cache->cache_value('forum_moderator_array', $moderatorsArray, 86200);
                }
            }
        }

        $userIds = $moderatorsArray[$forumId] ?? [];
        $names = [];
        foreach ($userIds as $userId) {
            $names[] = $plainText ? UserDisplay::plainUsername($userId) : UserDisplay::username($userId);
        }

        return rtrim(implode(', ', $names), ', ');
    }

    /**
     * Persist the moderator list for a forum.
     *
     * Mirrors `set_forum_moderators()`.
     */
    public static function setModerators(string $name, int|string $forumId, int $limit = 3): void
    {
        $name = rtrim(trim($name), ',');
        $users = explode(',', $name);
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = UserDisplay::userIdFromName(trim($user));
        }

        app(ForumRepository::class)->replaceModerators((int) $forumId, $userIds, $limit);
    }

    /**
     * Check whether the current user is a moderator for the given
     * post / topic / forum.
     *
     * Mirrors `is_forum_moderator()`.
     */
    public static function isModerator(int|string $id, string $in = 'post'): bool
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];

        $forumRep = app(ForumRepository::class);
        $userId = (int) ($CURUSER['id'] ?? 0);

        switch ($in) {
            case 'post':
                $topicId = $forumRep->getTopicIdByPost((int) $id);
                if ($topicId !== null) {
                    return self::isModerator($topicId, 'topic');
                }

                return false;

            case 'topic':
                return $forumRep->isModeratorOfTopic((int) $id, $userId);

            case 'forum':
                return $forumRep->isModeratorOfForum((int) $id, $userId);

            default:
                return false;
        }
    }

    /**
     * Check whether `$uid` may view the given post in a protected forum.
     *
     * Mirrors `can_view_post()`.
     */
    /**
     * @param  array<string, mixed>|int|string  $post
     */
    public static function canViewPost(int|string $uid, array|int|string $post): bool
    {
        /** @var array<int, Topic> $topics */
        static $topics = [];
        /** @var array<int, string>|null $protectedForumIds */
        static $protectedForumIds = null;
        /** @var array<int, int>|null $forumMods */
        static $forumMods = null;

        if (! is_array($post)) {
            $post = app(ForumRepository::class)->getPostArrayById((int) $post);
        }

        $topicId = $post['topicid'];
        if (! isset($topics[$topicId])) {
            $topics[$topicId] = app(ForumRepository::class)->getTopicById($topicId);
        }
        /** @var Topic $topicInfo */
        $topicInfo = $topics[$topicId];
        $forumId = $topicInfo->forumid;

        if ($protectedForumIds === null) {
            $protected = Cache::remember('setting_protected_forum', 600, function () {
                return app(SettingRepository::class)->getByName('misc.protected_forum') ?? false;
            });
            $protectedForumIds = $protected ? (preg_split('/[,\s]+/', $protected) ?: []) : [];
        }

        if ($forumMods === null) {
            $forumMods = app(ForumRepository::class)->getForumMods();
        }

        $isForumMod = isset($forumMods[$forumId]) && $forumMods[$forumId] == $uid;
        $log = sprintf(
            'uid: %s, class: %s, post: %s, forumId: %s, protectedForumIdArr: %s, forumMods: %s, isForumMod: %s',
            $uid,
            UserDisplay::currentClass(),
            $post['id'],
            $forumId,
            json_encode($protectedForumIds),
            json_encode($forumMods),
            $isForumMod
        );

        if (
            in_array($forumId, $protectedForumIds)
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

    /**
     * Fetch a post row, using the legacy cache layer.
     *
     * Mirrors `get_post_row()`.
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function postRow(?LegacyRedisCache $cache, int|string $postId): ?array
    {
        $cacheKey = 'post_'.$postId.'_content';
        $row = $cache !== null ? $cache->get_value($cacheKey) : false;

        if ($row === false) {
            $row = app(ForumRepository::class)->findPostArrayById((int) $postId);
            if ($cache !== null) {
                $cache->cache_value($cacheKey, $row, 7200);
            }
        }

        return $row ?: null;
    }

    /**
     * Context-aware wrapper for {@see postRow()}.
     *
     * @return array<string, mixed>|null
     */
    public static function postRowWithContext(int|string $postId): ?array
    {
        return self::postRow(app(LegacyRedisCache::class), $postId);
    }

    /**
     * Context-aware wrapper for {@see moderators()}.
     */
    public static function moderatorsWithContext(int|string $forumId, bool $plainText = true): string
    {
        return self::moderators(app(LegacyRedisCache::class), $forumId, $plainText);
    }
}
