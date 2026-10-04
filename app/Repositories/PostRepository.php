<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Models\Forum;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
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
     * @param  array<int, int>  $topicIds
     * @return array<int, int> map of topic id to post count
     */
    public function countTopicPostsBatch(array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds))));
        if ($topicIds === []) {
            return [];
        }

        return Post::query()
            ->whereIn('topicid', $topicIds)
            ->selectRaw('topicid, COUNT(*) as cnt')
            ->groupBy('topicid')
            ->pluck('cnt', 'topicid')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();
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

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int> map of user id to post count
     */
    public function countUserPostsBatch(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        return Post::query()
            ->whereIn('userid', $userIds)
            ->selectRaw('userid, COUNT(*) as cnt')
            ->groupBy('userid')
            ->pluck('cnt', 'userid')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();
    }

    public function updateUserLastPost(int $userId, string $date): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['last_post' => $date]);
    }

    public function deletePost(int $postid, int $topicid, int $forumid): bool
    {
        $deleted = Post::query()->where('id', $postid)->where('topicid', $topicid)->delete();
        if ($deleted === 0) {
            return false;
        }

        Forum::query()->where('id', $forumid)->update(['postcount' => DB::raw('GREATEST(CAST(postcount AS SIGNED) - 1, 0)')]);

        return true;
    }

    public function countForumSearchPosts(string $keywords, int $minClass): int
    {
        return (int) $this->forumSearchQuery($keywords, $minClass)->count('posts.id');
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): Collection
    {
        if ($perPage <= 0) {
            return new Collection;
        }

        return $this->forumSearchQuery($keywords, $minClass)
            ->select('posts.id', 'posts.topicid', 'posts.userid', 'posts.added', 'topics.subject', 'topics.hlcolor', 'forums.id AS forumid', 'forums.name AS forumname')
            ->orderByDesc('posts.id')
            ->offset($offset)
            ->limit($perPage)
            ->get();
    }

    /**
     * @return Builder
     */
    private function forumSearchQuery(string $keywords, int $minClass)
    {
        $term = '%'.$keywords.'%';

        return DB::table('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->leftJoin('forums', 'topics.forumid', '=', 'forums.id')
            ->where('forums.minclassread', '<=', $minClass)
            ->where(function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('topics.subject', 'like', $term)->whereColumn('posts.id', 'topics.firstpost');
                })->orWhere('posts.body', 'like', $term);
            });
    }

    public function getForumTodayPostCount(int $forumid, string $todayDate): int
    {
        return (int) DB::table('posts')
            ->leftJoin('topics', 'posts.topicid', '=', 'topics.id')
            ->where('posts.added', '>', $todayDate)
            ->where('topics.forumid', $forumid)
            ->count('posts.id');
    }

    public function findLastIdAddedBefore(string $before): ?int
    {
        $postId = Post::query()
            ->where('added', '<', $before)
            ->orderBy('added', 'desc')
            ->value('id');

        return $postId === null ? null : (int) $postId;
    }
}
