<?php

namespace App\Repositories;

use Nexus\Database\NexusDB;

class ForumRepository extends BaseRepository
{
    public function deleteForum(int $id): void
    {
        $topics = NexusDB::table('topics')->where('forumid', $id)->get(['id']);
        foreach ($topics as $topic) {
            NexusDB::table('posts')->where('topicid', $topic->id)->delete();
        }

        NexusDB::table('topics')->where('forumid', $id)->delete();
        NexusDB::table('forums')->where('id', $id)->delete();
        NexusDB::table('forummods')->where('forumid', $id)->delete();

        $this->clearForumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function updateForum(int $id, array $data): void
    {
        NexusDB::table('forums')->where('id', $id)->update($data);
        $this->clearForumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function createForum(array $data): int
    {
        $id = (int) NexusDB::table('forums')->insertGetId($data);
        $this->clearForumCache();

        return $id;
    }

    public function replaceModerators(int $forumId, array $userIds, int $limit = 3): void
    {
        NexusDB::table('forummods')->where('forumid', $forumId)->delete();

        $records = [];
        $max = min($limit, count($userIds));
        for ($i = 0; $i < $max; $i++) {
            $records[] = ['forumid' => $forumId, 'userid' => $userIds[$i]];
        }
        if (! empty($records)) {
            NexusDB::table('forummods')->insert($records);
        }

        $this->clearModeratorCache();
    }

    public function getOverforums(): array
    {
        return NexusDB::table('overforums')
            ->orderBy('sort')
            ->get(['id', 'name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getMaxForumSort(): int
    {
        return (int) NexusDB::table('forums')->count();
    }

    /** @return  array<string, mixed>|null */
    public function getForumRow(int $id): ?array
    {
        $row = (array) NexusDB::table('forums')->where('id', $id)->first();
        return empty($row) ? null : $row;
    }

    /** @return  array<int, array<string, mixed>> */
    public function getForumsWithOverforum(): array
    {
        return NexusDB::table('forums')
            ->leftJoin('overforums', 'forums.forid', '=', 'overforums.id')
            ->orderBy('forums.sort')
            ->get(['forums.*', 'overforums.name AS of_name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function deleteOverforum(int $id): void
    {
        NexusDB::table('overforums')->where('id', $id)->delete();
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function updateOverforum(int $id, array $data): void
    {
        NexusDB::table('overforums')->where('id', $id)->update($data);
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function createOverforum(array $data): void
    {
        NexusDB::table('overforums')->insert($data);
        $this->clearOverforumCache();
    }

    public function getMaxOverforumSort(): int
    {
        return (int) NexusDB::table('overforums')->count();
    }

    /** @return  array<string, mixed>|null */
    public function getOverforumRow(int $id): ?array
    {
        $row = (array) NexusDB::table('overforums')->where('id', $id)->first();
        return empty($row) ? null : $row;
    }

    /** @return  array<int, array<string, mixed>> */
    public function getAllOverforums(): array
    {
        return NexusDB::table('overforums')
            ->orderBy('sort')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** @return  array<int, array<int>> */
    public function getModeratorArray(): array
    {
        $array = [];
        foreach (NexusDB::table('forummods')->orderBy('forumid')->get(['forumid', 'userid']) as $row) {
            $row = (array) $row;
            $array[$row['forumid']][] = $row['userid'];
        }

        return $array;
    }

    public function clearForumCache(): void
    {
        NexusDB::cache_del('forums_list');
        NexusDB::cache_del('forum_moderator_array');
    }

    public function clearOverforumCache(): void
    {
        NexusDB::cache_del('overforums_list');
    }

    public function clearModeratorCache(): void
    {
        NexusDB::cache_del('forum_moderator_array');
    }

    public function getTopicIdByPost(int $postId): ?int
    {
        $topicId = \App\Models\Post::query()->where('id', $postId)->value('topicid');
        return $topicId === null ? null : (int) $topicId;
    }

    public function isModeratorOfTopic(int $topicId, int $userId): bool
    {
        return (int) NexusDB::table('forummods')
            ->selectRaw('COUNT(forummods.userid) AS count')
            ->leftJoin('topics', 'forummods.forumid', '=', 'topics.forumid')
            ->where('topics.id', $topicId)
            ->where('forummods.userid', $userId)
            ->value('count') > 0;
    }

    public function isModeratorOfForum(int $forumId, int $userId): bool
    {
        return \App\Models\ForumMod::query()
            ->where('forumid', $forumId)
            ->where('userid', $userId)
            ->exists();
    }
}
