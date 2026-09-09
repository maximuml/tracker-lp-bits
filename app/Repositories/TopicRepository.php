<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Topic repository: reads, create/edit/delete, and moderation for forum topics.
 */
class TopicRepository extends BaseRepository
{
    public function getTopicIdByPost(int $postId): ?int
    {
        $topicId = Post::query()->where('id', $postId)->value('topicid');

        return $topicId === null ? null : (int) $topicId;
    }

    public function isModeratorOfTopic(int $topicId, int $userId): bool
    {
        return (int) DB::table('forummods')
            ->selectRaw('COUNT(forummods.userid) AS count')
            ->leftJoin('topics', 'forummods.forumid', '=', 'topics.forumid')
            ->where('topics.id', $topicId)
            ->where('forummods.userid', $userId)
            ->value('count') > 0;
    }

    public function getTotalTopicsCount(): int
    {
        return (int) Topic::query()->count();
    }

    public function topicExists(int $id): ?int
    {
        $topic = Topic::query()->where('id', $id)->first(['forumid']);

        return $topic ? (int) $topic->forumid : null;
    }

    public function updateTopicLastPost(int $topicId): bool
    {
        $postId = Post::query()->where('topicid', $topicId)->orderByDesc('id')->value('id');

        if (! $postId) {
            return false;
        }

        return (bool) Topic::query()->where('id', $topicId)->update(['lastpost' => $postId]);
    }

    public function getTopicSubject(int $id): ?string
    {
        return Topic::query()->where('id', $id)->value('subject');
    }

    public function getTopicForumId(int $topicid): ?int
    {
        return Topic::query()->where('id', $topicid)->value('forumid');
    }

    public function isTopicLocked(int $topicid): ?bool
    {
        $topic = Topic::query()->where('id', $topicid)->first(['locked']);

        return $topic?->locked;
    }

    public function getTopic(int $id): ?Topic
    {
        return Topic::query()->where('id', $id)->first();
    }

    public function getTopicWithUser(int $id): ?Topic
    {
        return Topic::query()->with('user')->where('id', $id)->first();
    }

    public function updateTopicSubject(int $topicid, string $subject): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['subject' => $subject]);
    }

    public function createTopic(int $userId, int $forumId, string $subject): int
    {
        $topic = Topic::create([
            'userid' => $userId,
            'forumid' => $forumId,
            'subject' => $subject,
            'locked' => false,
            'sticky' => false,
            'hlcolor' => 0,
            'views' => 0,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);

        return (int) $topic->id;
    }

    public function updateTopicFirstLastPost(int $topicid, int $postid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['firstpost' => $postid, 'lastpost' => $postid]);
    }

    public function setTopicLastPost(int $topicid, int $postid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['lastpost' => $postid]);
    }

    public function incrementTopicViews(int $topicid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->increment('views');
    }

    public function moveTopic(int $topicid, int $newForumid, int $postCount, int $oldForumid): bool
    {
        if ($oldForumid == $newForumid) {
            return true;
        }

        Topic::query()->where('id', $topicid)->update(['forumid' => $newForumid]);
        Forum::query()->where('id', $oldForumid)->decrement('topiccount');
        Forum::query()->where('id', $oldForumid)->decrement('postcount', $postCount);
        Forum::query()->where('id', $newForumid)->increment('topiccount');
        Forum::query()->where('id', $newForumid)->increment('postcount', $postCount);

        return true;
    }

    /**
     * @return array<string, int>|null
     */
    public function getTopicForumAndUser(int $topicid): ?array
    {
        $topic = Topic::query()->where('id', $topicid)->first(['forumid', 'userid']);

        return $topic ? [
            'forumid' => (int) $topic->forumid,
            'userid' => (int) $topic->userid,
        ] : null;
    }

    public function deleteTopic(int $topicid, int $forumid, int $postCount): bool
    {
        Topic::query()->where('id', $topicid)->delete();
        Post::query()->where('topicid', $topicid)->delete();
        DB::table('readposts')->where('topicid', $topicid)->delete();
        Forum::query()->where('id', $forumid)->decrement('topiccount');
        Forum::query()->where('id', $forumid)->decrement('postcount', $postCount);

        return true;
    }

    public function updateTopicLocked(int $topicid, bool $locked): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['locked' => $locked]);
    }

    public function updateTopicSticky(int $topicid, string $sticky): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['sticky' => $sticky]);
    }

    public function updateTopicHighlight(int $topicid, int $color): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['hlcolor' => $color]);
    }

    /**
     * @return array{count: int, rows: Collection<int, Topic>}
     */
    public function getTopicsByForum(int $forumid, string $search, string $sortColumn, string $direction, int $offset, int $perPage): array
    {
        $allowed = ['firstpost' => 'firstpost', 'lastpost' => 'lastpost'];
        $column = $allowed[$sortColumn] ?? 'lastpost';
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $query = Topic::query()->where('forumid', $forumid);
        if ($search !== '') {
            $query->where('subject', 'like', '%'.$search.'%');
        }

        $count = (int) $query->count();
        $rows = $query->orderBy('sticky', 'desc')->orderBy($column, $direction)->offset($offset)->limit($perPage)
            ->with(['user', 'forum', 'firstPost.user', 'lastPost.user'])
            ->get();

        return ['count' => $count, 'rows' => $rows];
    }

    /**
     * @return Collection<int, Topic>
     */
    public function getUnreadTopics(int $lastCatchup, ?int $beforePostId, int $limit): Collection
    {
        $query = Topic::query()->where('lastpost', '>', $lastCatchup);
        if ($beforePostId) {
            $query->where('lastpost', '<', $beforePostId);
        }

        return $query->orderByDesc('lastpost')->with(['user', 'forum', 'lastPost.user'])->limit($limit)->get();
    }

    public function getTopicById(int $id): Topic
    {
        return Topic::query()->findOrFail($id);
    }

    public function getLastTopicByForum(int $forumid): ?Topic
    {
        return Topic::query()->where('forumid', $forumid)->orderByDesc('lastpost')->first();
    }
}
