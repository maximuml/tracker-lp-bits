<?php

namespace App\Support;

use Nexus\Database\NexusDB;

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
        return 'pic/forum_pic/' . $langFolder;
    }

    /**
     * Return a comma-separated list of moderators for the given forum.
     *
     * Mirrors `get_forum_moderators()`.
     */
    public static function moderators($cache, int|string $forumId, bool $plainText = true): string
    {
        static $moderatorsArray = null;

        if ($moderatorsArray === null) {
            $cached = method_exists($cache, 'get_value') ? $cache->get_value('forum_moderator_array') : false;
            if ($cached !== false && is_array($cached)) {
                $moderatorsArray = $cached;
            } else {
                $moderatorsArray = [];
                $result = NexusDB::getInstance()->query('SELECT forumid, userid FROM forummods ORDER BY forumid ASC');
                while ($row = NexusDB::getInstance()->fetchAssoc($result)) {
                    $moderatorsArray[$row['forumid']][] = $row['userid'];
                }
                if (method_exists($cache, 'cache_value')) {
                    $cache->cache_value('forum_moderator_array', $moderatorsArray, 86200);
                }
            }
        }

        $userIds = $moderatorsArray[$forumId] ?? [];
        $names = [];
        foreach ($userIds as $userId) {
            $names[] = $plainText ? \get_plain_username($userId) : \get_username($userId);
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
            $userIds[] = \get_user_id_from_name(trim($user));
        }

        $max = count($userIds);
        NexusDB::getInstance()->query('DELETE FROM forummods WHERE forumid=' . \App\Support\LegacyDb::escape($forumId));
        for ($i = 0; $i < $limit && $i < $max; $i++) {
            NexusDB::getInstance()->query('INSERT INTO forummods (forumid, userid) VALUES (' . \App\Support\LegacyDb::escape($forumId) . ',' . \App\Support\LegacyDb::escape($userIds[$i]) . ')');
        }
    }

    /**
     * Check whether the current user is a moderator for the given
     * post / topic / forum.
     *
     * Mirrors `is_forum_moderator()`.
     */
    public static function isModerator(int|string $id, string $in = 'post'): bool
    {
        $CURUSER = $GLOBALS['CURUSER'] ?? [];

        switch ($in) {
            case 'post':
                $result = NexusDB::getInstance()->query('SELECT topicid FROM posts WHERE id=' . (int) $id);
                if ($row = NexusDB::getInstance()->fetchAssoc($result)) {
                    return self::isModerator($row['topicid'], 'topic');
                }
                return false;

            case 'topic':
                $count = (int) NexusDB::table('forummods')
                    ->selectRaw('COUNT(forummods.userid) AS count')
                    ->leftJoin('topics', 'forummods.forumid', '=', 'topics.forumid')
                    ->where('topics.id', $id)
                    ->where('forummods.userid', $CURUSER['id'] ?? 0)
                    ->value('count');
                return $count > 0;

            case 'forum':
                $count = (int) \get_row_count('forummods', 'WHERE forumid=' . (int) $id . ' AND userid=' . \App\Support\LegacyDb::escape($CURUSER['id'] ?? 0));
                return $count > 0;

            default:
                return false;
        }
    }

    /**
     * Check whether `$uid` may view the given post in a protected forum.
     *
     * Mirrors `can_view_post()`.
     */
    public static function canViewPost(int|string $uid, array|int|string $post): bool
    {
        static $topics = [];
        static $protectedForumIds = null;
        static $forumMods = null;

        if (! is_array($post)) {
            $post = \App\Models\Post::query()->findOrFail((int) $post)->toArray();
        }

        $topicId = $post['topicid'];
        if (! isset($topics[$topicId])) {
            $topics[$topicId] = \App\Models\Topic::query()->findOrFail($topicId);
        }
        /** @var \App\Models\Topic $topicInfo */
        $topicInfo = $topics[$topicId];
        $forumId = $topicInfo->forumid;

        if ($protectedForumIds === null) {
            $protected = \Nexus\Database\NexusDB::remember('setting_protected_forum', 600, function () {
                return \App\Models\Setting::getByName('misc.protected_forum');
            });
            $protectedForumIds = $protected ? preg_split('/[,\s]+/', $protected) : [];
        }

        if ($forumMods === null) {
            $forumMods = [];
            foreach (\App\Models\ForumMod::query()->get() as $item) {
                $forumMods[$item->forumid] = $item->userid;
            }
        }

        $isForumMod = isset($forumMods[$forumId]) && $forumMods[$forumId] == $uid;
        $log = sprintf(
            'uid: %s, class: %s, post: %s, forumId: %s, protectedForumIdArr: %s, forumMods: %s, isForumMod: %s',
            $uid,
            \get_user_class(),
            $post['id'],
            $forumId,
            json_encode($protectedForumIds),
            json_encode($forumMods),
            $isForumMod
        );

        if (
            in_array($forumId, $protectedForumIds)
            && \get_user_class() < \App\Models\User::CLASS_ADMINISTRATOR
            && $uid != $post['userid']
            && $uid != $topicInfo->userid
            && ! $isForumMod
        ) {
            \do_log("$log, FALSE");
            return false;
        }

        \do_log("$log, TRUE");
        return true;
    }

    /**
     * Fetch a post row, using the legacy cache layer.
     *
     * Mirrors `get_post_row()`.
     */
    public static function postRow($cache, int|string $postId): ?array
    {
        $cacheKey = 'post_' . $postId . '_content';
        $row = method_exists($cache, 'get_value') ? $cache->get_value($cacheKey) : false;

        if ($row === false) {
            $result = NexusDB::getInstance()->query('SELECT * FROM posts WHERE id=' . \App\Support\LegacyDb::escape($postId) . ' LIMIT 1');
            $row = NexusDB::getInstance()->fetchAssoc($result);
            if (method_exists($cache, 'cache_value')) {
                $cache->cache_value($cacheKey, $row, 7200);
            }
        }

        return $row ?: null;
    }
}
