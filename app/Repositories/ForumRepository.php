<?php

namespace App\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
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

    /**
     * @param  array<int>  $userIds
     */
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

    /**
     * @return  array<int, array<string, mixed>>
     */
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

    /**
     * @return  array<int, array<string, mixed>>
     */
    public function getAllOverforums(): array
    {
        return self::getOverforumsList();
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getOverforumsList(): array
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

    public static function getActiveForumUserCount(): int
    {
        $secs = 900;
        $dt = date("Y-m-d H:i:s", (time() - $secs));

        return (int) User::query()->where('forum_access', '>=', $dt)->count();
    }

    public static function getTotalPostsCount(): int
    {
        return (int) Post::query()->count();
    }

    public static function getTotalTopicsCount(): int
    {
        return (int) Topic::query()->count();
    }

    public static function getTodayPostsCount(string $todayDate): int
    {
        return (int) Post::query()->where('added', '>', date("Y-m-d"))->count();
    }

    public static function clearReadPosts(int $userId): void
    {
        NexusDB::table('readposts')->where('userid', $userId)->delete();
    }

    public static function getLastPostId(): ?int
    {
        $value = Post::query()->orderByDesc('id')->value('id');

        return $value === null ? null : (int) $value;
    }

    public static function updateLastCatchup(int $userId, int $lastPostId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_catchup' => $lastPostId]);
    }

    public static function forumExists(int $id): bool
    {
        return (bool) Forum::query()->where('id', $id)->exists();
    }

    public static function topicExists(int $id): ?int
    {
        $topic = Topic::query()->where('id', $id)->first(['forumid']);

        return $topic ? (int) $topic->forumid : null;
    }

    public static function postExists(int $id): ?int
    {
        $post = Post::query()->where('id', $id)->first(['topicid']);

        return $post ? (int) $post->topicid : null;
    }

    public static function updateTopicLastPost(int $topicId): bool
    {
        $postId = Post::query()->where('topicid', $topicId)->orderByDesc('id')->value('id');

        if (!$postId) {
            return false;
        }

        return (bool) Topic::query()->where('id', $topicId)->update(['lastpost' => $postId]);
    }

    /**
     * @return  array<int, array<string, mixed>>
     */
    public static function getForumsList(): array
    {
        return Forum::query()->orderBy('forid')->orderBy('sort')->get()->keyBy('id')->map(fn ($f) => $f->toArray())->all();
    }

    /**
     * @return  array<int, int>|null
     */
    public static function getLastReadPosts(int $userId): ?array
    {
        $rows = NexusDB::table('readposts')->where('userid', $userId)->get(['topicid', 'lastpostread']);

        if ($rows->isEmpty()) {
            return null;
        }

        $ret = [];
        foreach ($rows as $row) {
            $ret[(int) $row->topicid] = (int) $row->lastpostread;
        }

        return $ret;
    }

    public static function getForumName(int $id): ?string
    {
        return Forum::query()->where('id', $id)->value('name');
    }

    public static function getTopicSubject(int $id): ?string
    {
        return Topic::query()->where('id', $id)->value('subject');
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getPostForQuote(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body', 'userid']);
        if (!$post) {
            return null;
        }
        $topic = Topic::query()->where('id', $post->topicid)->first(['subject']);
        $username = User::query()->where('id', $post->userid)->value('username');

        return [
            'topicid' => (int) $post->topicid,
            'body' => (string) $post->body,
            'userid' => (int) $post->userid,
            'username' => $username,
            'topic_subject' => $topic ? $topic->subject : null,
        ];
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getPostForEdit(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body']);
        if (!$post) {
            return null;
        }
        $topicid = (int) $post->topicid;
        $firstpost = (int) Post::query()->where('topicid', $topicid)->min('id');
        $topic = Topic::query()->where('id', $topicid)->first(['subject']);

        return [
            'topicid' => $topicid,
            'body' => (string) $post->body,
            'firstpost' => $firstpost,
            'topic_subject' => $topic ? $topic->subject : null,
            'is_first_post' => $firstpost == $id,
        ];
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getPostWithTopic(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['userid', 'topicid']);
        if (!$post) {
            return null;
        }
        $topic = Topic::query()->where('id', $post->topicid)->first(['locked']);

        return [
            'userid' => (int) $post->userid,
            'topicid' => (int) $post->topicid,
            'locked' => $topic ? $topic->locked : null,
        ];
    }

    public static function getTopicForumId(int $topicid): ?int
    {
        return Topic::query()->where('id', $topicid)->value('forumid');
    }

    /**
     * @return  array<string, mixed>|null
     */
    public static function getPostEditInfo(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid']);
        if (!$post) {
            return null;
        }
        $topicid = (int) $post->topicid;
        $topic = Topic::query()->where('id', $topicid)->first(['forumid']);
        $firstpost = (int) Post::query()->where('topicid', $topicid)->min('id');

        return [
            'topicid' => $topicid,
            'forumid' => $topic ? (int) $topic->forumid : 0,
            'is_first_post' => $firstpost == $postid,
        ];
    }

    public static function isTopicLocked(int $topicid): ?string
    {
        return Topic::query()->where('id', $topicid)->value('locked');
    }

    public static function getTopic(int $id): ?Topic
    {
        return Topic::query()->where('id', $id)->first();
    }

    public static function getPost(int $id): ?Post
    {
        return Post::query()->where('id', $id)->first();
    }

    public static function getTopicWithUser(int $id): ?Topic
    {
        return Topic::query()->with('user')->where('id', $id)->first();
    }

    public static function getPostWithUser(int $id): ?Post
    {
        return Post::query()->with('user')->where('id', $id)->first();
    }

    public static function updateTopicSubject(int $topicid, string $subject): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['subject' => $subject]);
    }

    public static function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool
    {
        return (bool) Post::query()->where('id', $postid)->update([
            'body' => $body,
            'editdate' => $date,
            'editedby' => $editedBy,
        ]);
    }

    public static function getFirstPostId(int $topicid): int
    {
        return (int) Post::query()->where('topicid', $topicid)->min('id');
    }

    public static function createTopic(int $userId, int $forumId, string $subject): int
    {
        $topic = Topic::create([
            'userid' => $userId,
            'forumid' => $forumId,
            'subject' => $subject,
            'locked' => 'no',
            'sticky' => 'no',
            'hlcolor' => 0,
            'views' => 0,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);

        return (int) $topic->id;
    }

    public static function incrementForumTopicCount(int $forumid): bool
    {
        return (bool) Forum::query()->where('id', $forumid)->increment('topiccount');
    }

    public static function incrementForumPostCount(int $forumid, int $amount = 1): bool
    {
        return (bool) Forum::query()->where('id', $forumid)->increment('postcount', $amount);
    }

    public static function createPost(int $topicId, int $userId, string $body, string $date): int
    {
        return (int) NexusDB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $userId,
            'added' => $date,
            'body' => $body,
            'ori_body' => $body,
        ]);
    }

    public static function updateTopicFirstLastPost(int $topicid, int $postid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['firstpost' => $postid, 'lastpost' => $postid]);
    }

    public static function setTopicLastPost(int $topicid, int $postid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['lastpost' => $postid]);
    }

    public static function incrementTopicViews(int $topicid): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->increment('views');
    }

    public static function countTopicPosts(int $topicid, ?int $authorId = null): int
    {
        $query = Post::query()->where('topicid', $topicid);
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return (int) $query->count();
    }

    /**
     * @return  array<int>
     */
    public static function getTopicPostIds(int $topicid, ?int $authorId = null): array
    {
        $query = Post::query()->where('topicid', $topicid)->orderBy('added');
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return $query->pluck('id')->all();
    }

    /**
     * @return  \Illuminate\Database\Eloquent\Collection<int, \App\Models\Post>
     */
    public static function getTopicPosts(int $topicid, ?int $authorId, int $offset, int $perPage): \Illuminate\Database\Eloquent\Collection
    {
        $query = Post::query()->with('user')->where('topicid', $topicid)->orderBy('id');
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return $query->offset($offset)->limit($perPage)->get();
    }

    /**
     * @param  array<int> $ids
     * @param  list<string> $columns
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public static function getUsersByIds(array $ids, array $columns): \Illuminate\Support\Collection
    {
        return User::query()->find($ids, $columns)->keyBy('id');
    }

    public static function getReadPost(int $userId, int $topicId): ?\stdClass
    {
        return NexusDB::table('readposts')
            ->where('userid', $userId)
            ->where('topicid', $topicId)
            ->first();
    }

    public static function insertReadPost(int $userId, int $topicId, int $postId): bool
    {
        return (bool) NexusDB::table('readposts')->insert([
            'userid' => $userId,
            'topicid' => $topicId,
            'lastpostread' => $postId,
        ]);
    }

    public static function updateReadPost(int $userId, int $topicId, int $postId): bool
    {
        return (bool) NexusDB::table('readposts')
            ->where('userid', $userId)
            ->where('topicid', $topicId)
            ->update(['lastpostread' => $postId]);
    }

    public static function countUserPosts(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public static function markPostRead(int $userId, int $topicId, int $postId, int $lastCatchup): bool
    {
        $readPost = NexusDB::table('readposts')
            ->where('userid', $userId)
            ->where('topicid', $topicId)
            ->first();

        if (!$readPost) {
            return (bool) NexusDB::table('readposts')->insert([
                'userid' => $userId,
                'topicid' => $topicId,
                'lastpostread' => $postId,
            ]);
        }

        if ($lastCatchup < $postId) {
            return (bool) NexusDB::table('readposts')
                ->where('userid', $userId)
                ->where('topicid', $topicId)
                ->update(['lastpostread' => $postId]);
        }

        return true;
    }

    public static function updateUserLastPost(int $userId, string $date): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_post' => $date]);
    }

    public static function getForumMinclasswrite(int $forumid): ?int
    {
        $forum = Forum::query()->where('id', $forumid)->first(['minclasswrite']);

        return $forum ? (int) $forum->minclasswrite : null;
    }

    public static function moveTopic(int $topicid, int $newForumid, int $postCount, int $oldForumid): bool
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
     * @return  array<string, int>|null
     */
    public static function getTopicForumAndUser(int $topicid): ?array
    {
        $topic = Topic::query()->where('id', $topicid)->first(['forumid', 'userid']);

        return $topic ? [
            'forumid' => (int) $topic->forumid,
            'userid' => (int) $topic->userid,
        ] : null;
    }

    public static function deleteTopic(int $topicid, int $forumid, int $postCount): bool
    {
        Topic::query()->where('id', $topicid)->delete();
        Post::query()->where('topicid', $topicid)->delete();
        NexusDB::table('readposts')->where('topicid', $topicid)->delete();
        Forum::query()->where('id', $forumid)->decrement('topiccount');
        Forum::query()->where('id', $forumid)->decrement('postcount', $postCount);

        return true;
    }

    /**
     * @return  array{topicid: int, userid: int}|null
     */
    public static function getPostTopicAndUser(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid', 'userid']);

        return $post ? [
            'topicid' => (int) $post->topicid,
            'userid' => (int) $post->userid,
        ] : null;
    }

    public static function getPreviousPostId(int $topicid, int $postid): ?int
    {
        return Post::query()
            ->where('topicid', $topicid)
            ->where('id', '<', $postid)
            ->orderByDesc('id')
            ->value('id');
    }

    public static function deletePost(int $postid, int $topicid, int $forumid): bool
    {
        Post::query()->where('id', $postid)->delete();
        Forum::query()->where('id', $forumid)->decrement('postcount');

        return true;
    }

    public static function updateTopicLocked(int $topicid, string $locked): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['locked' => $locked]);
    }

    public static function updateTopicSticky(int $topicid, string $sticky): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['sticky' => $sticky]);
    }

    public static function updateTopicHighlight(int $topicid, int $color): bool
    {
        return (bool) Topic::query()->where('id', $topicid)->update(['hlcolor' => $color]);
    }

    public static function updateUserForumAccess(int $userId, string $date): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['forum_access' => $date]);
    }

    /**
     * @return  array{count: int, rows: \Illuminate\Support\Collection<int, \App\Models\Topic>}
     */
    public static function getTopicsByForum(int $forumid, string $search, string $sortColumn, string $direction, int $offset, int $perPage): array
    {
        $allowed = ['firstpost' => 'firstpost', 'lastpost' => 'lastpost'];
        $column = $allowed[$sortColumn] ?? 'lastpost';
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true) ? strtolower($direction) : 'desc';

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
     * @return  \Illuminate\Support\Collection<int, \App\Models\Topic>
     */
    public static function getUnreadTopics(int $lastCatchup, ?int $beforePostId, int $limit): \Illuminate\Support\Collection
    {
        $query = Topic::query()->where('lastpost', '>', $lastCatchup);
        if ($beforePostId) {
            $query->where('lastpost', '<', $beforePostId);
        }

        return $query->orderByDesc('lastpost')->with(['user', 'forum', 'lastPost.user'])->limit($limit)->get();
    }

    /**
     * @return  array{hits: int, rows: \Illuminate\Support\Collection<int, \stdClass>}
     */
    public static function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): array
    {
        $term = '%'.$keywords.'%';
        $query = NexusDB::table('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->leftJoin('forums', 'topics.forumid', '=', 'forums.id')
            ->where('forums.minclassread', '<=', $minClass)
            ->where(function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('topics.subject', 'like', $term)->whereColumn('posts.id', 'topics.firstpost');
                })->orWhere('posts.body', 'like', $term);
            });

        $hits = (int) $query->count('posts.id');
        $rows = $query
            ->select('posts.id', 'posts.topicid', 'posts.userid', 'posts.added', 'topics.subject', 'topics.hlcolor', 'forums.id AS forumid', 'forums.name AS forumname')
            ->orderByDesc('posts.id')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return ['hits' => $hits, 'rows' => $rows];
    }

    public static function getLastTopicByForum(int $forumid): ?Topic
    {
        return Topic::query()->where('forumid', $forumid)->orderByDesc('lastpost')->first();
    }

    public static function getForumTodayPostCount(int $forumid, string $todayDate): int
    {
        return (int) NexusDB::table('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->where('posts.added', '>', $todayDate)
            ->where('topics.forumid', $forumid)
            ->count('posts.id');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPostArrayById(int $id): array
    {
        return Post::query()->findOrFail($id)->toArray();
    }

    public static function getTopicById(int $id): Topic
    {
        return Topic::query()->findOrFail($id);
    }

    /**
     * @return array<int, int>
     */
    public static function getForumMods(): array
    {
        $mods = [];
        foreach (\App\Models\ForumMod::query()->get() as $item) {
            $mods[(int) $item->forumid] = (int) $item->userid;
        }

        return $mods;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findPostArrayById(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first();

        return $post ? $post->toArray() : null;
    }
}
