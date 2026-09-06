<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PostService
{
    public function getTotalPostsCount(): int
    {
        return (int) Post::query()->count();
    }

    public function getTodayPostsCount(string $todayDate): int
    {
        return (int) Post::query()->where('added', '>', date('Y-m-d'))->count();
    }

    public function getLastPostId(): ?int
    {
        $value = Post::query()->orderByDesc('id')->value('id');

        return $value === null ? null : (int) $value;
    }

    public function updateLastCatchup(int $userId, int $lastPostId): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_catchup' => $lastPostId]);
    }

    public function postExists(int $id): ?int
    {
        $post = Post::query()->where('id', $id)->first(['topicid']);

        return $post ? (int) $post->topicid : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostForQuote(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body', 'userid']);
        if (! $post) {
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
     * @return array<string, mixed>|null
     */
    public function getPostForEdit(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first(['topicid', 'body']);
        if (! $post) {
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
     * @return array<string, mixed>|null
     */
    public function getPostWithTopic(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['userid', 'topicid']);
        if (! $post) {
            return null;
        }
        $topic = Topic::query()->where('id', $post->topicid)->first(['locked']);

        return [
            'userid' => (int) $post->userid,
            'topicid' => (int) $post->topicid,
            'locked' => $topic ? $topic->locked : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostEditInfo(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid']);
        if (! $post) {
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

    public function getPost(int $id): ?Post
    {
        return Post::query()->where('id', $id)->first();
    }

    public function getPostWithUser(int $id): ?Post
    {
        return Post::query()->with('user')->where('id', $id)->first();
    }

    public function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool
    {
        return (bool) Post::query()->where('id', $postid)->update([
            'body' => $body,
            'editdate' => $date,
            'editedby' => $editedBy,
        ]);
    }

    public function getFirstPostId(int $topicid): int
    {
        return (int) Post::query()->where('topicid', $topicid)->min('id');
    }

    public function createPost(int $topicId, int $userId, string $body, string $date): int
    {
        return (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $userId,
            'added' => $date,
            'body' => $body,
            'ori_body' => $body,
        ]);
    }

    public function countTopicPosts(int $topicid, ?int $authorId = null): int
    {
        $query = Post::query()->where('topicid', $topicid);
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return (int) $query->count();
    }

    /**
     * @return array<int>
     */
    public function getTopicPostIds(int $topicid, ?int $authorId = null): array
    {
        $query = Post::query()->where('topicid', $topicid)->orderBy('added');
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return $query->pluck('id')->all();
    }

    /**
     * @return EloquentCollection<int, Post>
     */
    public function getTopicPosts(int $topicid, ?int $authorId, int $offset, int $perPage): EloquentCollection
    {
        $query = Post::query()->with('user')->where('topicid', $topicid)->orderBy('id');
        if ($authorId) {
            $query->where('userid', $authorId);
        }

        return $query->offset($offset)->limit($perPage)->get();
    }

    public function countUserPosts(int $userId): int
    {
        return (int) Post::query()->where('userid', $userId)->count();
    }

    public function updateUserLastPost(int $userId, string $date): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_post' => $date]);
    }

    /**
     * @return array{topicid: int, userid: int}|null
     */
    public function getPostTopicAndUser(int $postid): ?array
    {
        $post = Post::query()->where('id', $postid)->first(['topicid', 'userid']);

        return $post ? [
            'topicid' => (int) $post->topicid,
            'userid' => (int) $post->userid,
        ] : null;
    }

    public function getPreviousPostId(int $topicid, int $postid): ?int
    {
        return Post::query()
            ->where('topicid', $topicid)
            ->where('id', '<', $postid)
            ->orderByDesc('id')
            ->value('id');
    }

    public function deletePost(int $postid, int $topicid, int $forumid): bool
    {
        Post::query()->where('id', $postid)->delete();
        Forum::query()->where('id', $forumid)->decrement('postcount');

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostArrayById(int $id): array
    {
        return Post::query()->findOrFail($id)->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPostArrayById(int $id): ?array
    {
        $post = Post::query()->where('id', $id)->first();

        return $post ? $post->toArray() : null;
    }

    /**
     * @return array{hits: int, rows: Collection<int, \stdClass>}
     */
    public function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): array
    {
        $term = '%'.$keywords.'%';
        $query = DB::table('posts')
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

    public function getForumTodayPostCount(int $forumid, string $todayDate): int
    {
        return (int) DB::table('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->where('posts.added', '>', $todayDate)
            ->where('topics.forumid', $forumid)
            ->count('posts.id');
    }
}
