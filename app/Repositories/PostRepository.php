<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Models\Forum;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Post repository: counters, topic listings, create/edit/delete, and search
 * for forum posts. Shaped single-post reads live in PostLookupRepository.
 */
class PostRepository extends BaseRepository implements PostRepositoryInterface
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

    public function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool
    {
        return (bool) Post::query()->where('id', $postid)->update([
            'body' => $body,
            'editdate' => $date,
            'editedby' => $editedBy,
        ]);
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

    public function deletePost(int $postid, int $topicid, int $forumid): bool
    {
        Post::query()->where('id', $postid)->delete();
        Forum::query()->where('id', $forumid)->decrement('postcount');

        return true;
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
