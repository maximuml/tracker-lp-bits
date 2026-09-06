<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Forum;
use App\Models\ForumMod;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Services\PostService;
use App\Services\TopicService;
use App\Support\Cache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForumRepository extends BaseRepository
{
    public function __construct(
        private readonly TopicService $topicService,
        private readonly PostService $postService,
    ) {}

    public function deleteForum(int $id): void
    {
        $topics = DB::table('topics')->where('forumid', $id)->get(['id']);
        DB::table('posts')->whereIn('topicid', $topics->pluck('id')->toArray())->delete();

        DB::table('topics')->where('forumid', $id)->delete();
        DB::table('forums')->where('id', $id)->delete();
        DB::table('forummods')->where('forumid', $id)->delete();

        $this->clearForumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function updateForum(int $id, array $data): void
    {
        DB::table('forums')->where('id', $id)->update($data);
        $this->clearForumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function createForum(array $data): int
    {
        $id = (int) DB::table('forums')->insertGetId($data);
        $this->clearForumCache();

        return $id;
    }

    /**
     * @param  array<int>  $userIds
     */
    public function replaceModerators(int $forumId, array $userIds, int $limit = 3): void
    {
        DB::table('forummods')->where('forumid', $forumId)->delete();

        $records = [];
        $max = min($limit, count($userIds));
        for ($i = 0; $i < $max; $i++) {
            $records[] = ['forumid' => $forumId, 'userid' => $userIds[$i]];
        }
        if (! empty($records)) {
            DB::table('forummods')->insert($records);
        }

        $this->clearModeratorCache();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverforums(): array
    {
        return DB::table('overforums')
            ->orderBy('sort')
            ->get(['id', 'name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getMaxForumSort(): int
    {
        return (int) DB::table('forums')->count();
    }

    /** @return  array<string, mixed>|null */
    public function getForumRow(int $id): ?array
    {
        $row = (array) DB::table('forums')->where('id', $id)->first();

        return empty($row) ? null : $row;
    }

    /** @return  array<int, array<string, mixed>> */
    public function getForumsWithOverforum(): array
    {
        return DB::table('forums')
            ->leftJoin('overforums', 'forums.forid', '=', 'overforums.id')
            ->orderBy('forums.sort')
            ->get(['forums.*', 'overforums.name AS of_name'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function deleteOverforum(int $id): void
    {
        DB::table('overforums')->where('id', $id)->delete();
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function updateOverforum(int $id, array $data): void
    {
        DB::table('overforums')->where('id', $id)->update($data);
        $this->clearOverforumCache();
    }

    /** @param  array<string, mixed>  $data */
    public function createOverforum(array $data): void
    {
        DB::table('overforums')->insert($data);
        $this->clearOverforumCache();
    }

    public function getMaxOverforumSort(): int
    {
        return (int) DB::table('overforums')->count();
    }

    /** @return  array<string, mixed>|null */
    public function getOverforumRow(int $id): ?array
    {
        $row = (array) DB::table('overforums')->where('id', $id)->first();

        return empty($row) ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllOverforums(): array
    {
        return $this->getOverforumsList();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOverforumsList(): array
    {
        return DB::table('overforums')
            ->orderBy('sort')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /** @return  array<int, array<int>> */
    public function getModeratorArray(): array
    {
        $array = [];
        foreach (DB::table('forummods')->orderBy('forumid')->get(['forumid', 'userid']) as $row) {
            $row = (array) $row;
            $array[$row['forumid']][] = $row['userid'];
        }

        return $array;
    }

    public function clearForumCache(): void
    {
        Cache::forgetWithLocales('forums_list');
        Cache::forgetWithLocales('forum_moderator_array');
    }

    public function clearOverforumCache(): void
    {
        Cache::forgetWithLocales('overforums_list');
    }

    public function clearModeratorCache(): void
    {
        Cache::forgetWithLocales('forum_moderator_array');
    }

    public function isModeratorOfForum(int $forumId, int $userId): bool
    {
        return ForumMod::query()
            ->where('forumid', $forumId)
            ->where('userid', $userId)
            ->exists();
    }

    public function getActiveForumUserCount(): int
    {
        $secs = 900;
        $dt = date('Y-m-d H:i:s', (time() - $secs));

        return (int) User::query()->where('forum_access', '>=', $dt)->count();
    }

    public function forumExists(int $id): bool
    {
        return (bool) Forum::query()->where('id', $id)->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForumsList(): array
    {
        return Forum::query()->orderBy('forid')->orderBy('sort')->get()->keyBy('id')->map(fn ($f) => $f->toArray())->all();
    }

    public function getForumName(int $id): ?string
    {
        return Forum::query()->where('id', $id)->value('name');
    }

    public function incrementForumTopicCount(int $forumid): bool
    {
        return (bool) Forum::query()->where('id', $forumid)->increment('topiccount');
    }

    public function incrementForumPostCount(int $forumid, int $amount = 1): bool
    {
        return (bool) Forum::query()->where('id', $forumid)->increment('postcount', $amount);
    }

    public function getForumMinclasswrite(int $forumid): ?int
    {
        $forum = Forum::query()->where('id', $forumid)->first(['minclasswrite']);

        return $forum ? (int) $forum->minclasswrite : null;
    }

    /**
     * @return array<int, int>
     */
    public function getForumMods(): array
    {
        $mods = [];
        foreach (ForumMod::query()->get() as $item) {
            $mods[(int) $item->forumid] = (int) $item->userid;
        }

        return $mods;
    }

    public function updateUserForumAccess(int $userId, string $date): bool
    {
        return (bool) User::query()->where('id', $userId)->update(['forum_access' => $date]);
    }

    /**
     * @param  array<int>  $ids
     * @param  list<string>  $columns
     * @return Collection<int, User>
     */
    public function getUsersByIds(array $ids, array $columns): Collection
    {
        return User::query()->find($ids, $columns)->keyBy('id');
    }

    // ---- Topic operations (delegated to TopicService) ----

    public function getTopicIdByPost(int $postId): ?int
    {
        return $this->topicService->getTopicIdByPost($postId);
    }

    public function isModeratorOfTopic(int $topicId, int $userId): bool
    {
        return $this->topicService->isModeratorOfTopic($topicId, $userId);
    }

    public function getTotalTopicsCount(): int
    {
        return $this->topicService->getTotalTopicsCount();
    }

    public function topicExists(int $id): ?int
    {
        return $this->topicService->topicExists($id);
    }

    public function updateTopicLastPost(int $topicId): bool
    {
        return $this->topicService->updateTopicLastPost($topicId);
    }

    public function getTopicSubject(int $id): ?string
    {
        return $this->topicService->getTopicSubject($id);
    }

    public function getTopicForumId(int $topicid): ?int
    {
        return $this->topicService->getTopicForumId($topicid);
    }

    public function isTopicLocked(int $topicid): ?bool
    {
        return $this->topicService->isTopicLocked($topicid);
    }

    public function getTopic(int $id): ?Topic
    {
        return $this->topicService->getTopic($id);
    }

    public function getTopicWithUser(int $id): ?Topic
    {
        return $this->topicService->getTopicWithUser($id);
    }

    public function updateTopicSubject(int $topicid, string $subject): bool
    {
        return $this->topicService->updateTopicSubject($topicid, $subject);
    }

    public function createTopic(int $userId, int $forumId, string $subject): int
    {
        return $this->topicService->createTopic($userId, $forumId, $subject);
    }

    public function updateTopicFirstLastPost(int $topicid, int $postid): bool
    {
        return $this->topicService->updateTopicFirstLastPost($topicid, $postid);
    }

    public function setTopicLastPost(int $topicid, int $postid): bool
    {
        return $this->topicService->setTopicLastPost($topicid, $postid);
    }

    public function incrementTopicViews(int $topicid): bool
    {
        return $this->topicService->incrementTopicViews($topicid);
    }

    public function moveTopic(int $topicid, int $newForumid, int $postCount, int $oldForumid): bool
    {
        return $this->topicService->moveTopic($topicid, $newForumid, $postCount, $oldForumid);
    }

    /**
     * @return array<string, int>|null
     */
    public function getTopicForumAndUser(int $topicid): ?array
    {
        return $this->topicService->getTopicForumAndUser($topicid);
    }

    public function deleteTopic(int $topicid, int $forumid, int $postCount): bool
    {
        return $this->topicService->deleteTopic($topicid, $forumid, $postCount);
    }

    public function updateTopicLocked(int $topicid, bool $locked): bool
    {
        return $this->topicService->updateTopicLocked($topicid, $locked);
    }

    public function updateTopicSticky(int $topicid, string $sticky): bool
    {
        return $this->topicService->updateTopicSticky($topicid, $sticky);
    }

    public function updateTopicHighlight(int $topicid, int $color): bool
    {
        return $this->topicService->updateTopicHighlight($topicid, $color);
    }

    /**
     * @return array{count: int, rows: Collection<int, Topic>}
     */
    public function getTopicsByForum(int $forumid, string $search, string $sortColumn, string $direction, int $offset, int $perPage): array
    {
        return $this->topicService->getTopicsByForum($forumid, $search, $sortColumn, $direction, $offset, $perPage);
    }

    /**
     * @return Collection<int, Topic>
     */
    public function getUnreadTopics(int $lastCatchup, ?int $beforePostId, int $limit): Collection
    {
        return $this->topicService->getUnreadTopics($lastCatchup, $beforePostId, $limit);
    }

    public function getTopicById(int $id): Topic
    {
        return $this->topicService->getTopicById($id);
    }

    /**
     * @return array<int, int>|null
     */
    public function getLastReadPosts(int $userId): ?array
    {
        return $this->topicService->getLastReadPosts($userId);
    }

    public function getReadPost(int $userId, int $topicId): ?\stdClass
    {
        return $this->topicService->getReadPost($userId, $topicId);
    }

    public function insertReadPost(int $userId, int $topicId, int $postId): bool
    {
        return $this->topicService->insertReadPost($userId, $topicId, $postId);
    }

    public function updateReadPost(int $userId, int $topicId, int $postId): bool
    {
        return $this->topicService->updateReadPost($userId, $topicId, $postId);
    }

    public function markPostRead(int $userId, int $topicId, int $postId, int $lastCatchup): bool
    {
        return $this->topicService->markPostRead($userId, $topicId, $postId, $lastCatchup);
    }

    public function clearReadPosts(int $userId): void
    {
        $this->topicService->clearReadPosts($userId);
    }

    public function getLastTopicByForum(int $forumid): ?Topic
    {
        return $this->topicService->getLastTopicByForum($forumid);
    }

    // ---- Post operations (delegated to PostService) ----

    public function getTotalPostsCount(): int
    {
        return $this->postService->getTotalPostsCount();
    }

    public function getTodayPostsCount(string $todayDate): int
    {
        return $this->postService->getTodayPostsCount($todayDate);
    }

    public function getLastPostId(): ?int
    {
        return $this->postService->getLastPostId();
    }

    public function updateLastCatchup(int $userId, int $lastPostId): bool
    {
        return $this->postService->updateLastCatchup($userId, $lastPostId);
    }

    public function postExists(int $id): ?int
    {
        return $this->postService->postExists($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostForQuote(int $id): ?array
    {
        return $this->postService->getPostForQuote($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostForEdit(int $id): ?array
    {
        return $this->postService->getPostForEdit($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostWithTopic(int $postid): ?array
    {
        return $this->postService->getPostWithTopic($postid);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPostEditInfo(int $postid): ?array
    {
        return $this->postService->getPostEditInfo($postid);
    }

    public function getPost(int $id): ?Post
    {
        return $this->postService->getPost($id);
    }

    public function getPostWithUser(int $id): ?Post
    {
        return $this->postService->getPostWithUser($id);
    }

    public function updatePostBody(int $postid, string $body, string $date, int $editedBy): bool
    {
        return $this->postService->updatePostBody($postid, $body, $date, $editedBy);
    }

    public function getFirstPostId(int $topicid): int
    {
        return $this->postService->getFirstPostId($topicid);
    }

    public function createPost(int $topicId, int $userId, string $body, string $date): int
    {
        return $this->postService->createPost($topicId, $userId, $body, $date);
    }

    public function countTopicPosts(int $topicid, ?int $authorId = null): int
    {
        return $this->postService->countTopicPosts($topicid, $authorId);
    }

    /**
     * @return array<int>
     */
    public function getTopicPostIds(int $topicid, ?int $authorId = null): array
    {
        return $this->postService->getTopicPostIds($topicid, $authorId);
    }

    /**
     * @return EloquentCollection<int, Post>
     */
    public function getTopicPosts(int $topicid, ?int $authorId, int $offset, int $perPage): EloquentCollection
    {
        return $this->postService->getTopicPosts($topicid, $authorId, $offset, $perPage);
    }

    public function countUserPosts(int $userId): int
    {
        return $this->postService->countUserPosts($userId);
    }

    public function updateUserLastPost(int $userId, string $date): bool
    {
        return $this->postService->updateUserLastPost($userId, $date);
    }

    /**
     * @return array{topicid: int, userid: int}|null
     */
    public function getPostTopicAndUser(int $postid): ?array
    {
        return $this->postService->getPostTopicAndUser($postid);
    }

    public function getPreviousPostId(int $topicid, int $postid): ?int
    {
        return $this->postService->getPreviousPostId($topicid, $postid);
    }

    public function deletePost(int $postid, int $topicid, int $forumid): bool
    {
        return $this->postService->deletePost($postid, $topicid, $forumid);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostArrayById(int $id): array
    {
        return $this->postService->getPostArrayById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPostArrayById(int $id): ?array
    {
        return $this->postService->findPostArrayById($id);
    }

    /**
     * @return array{hits: int, rows: Collection<int, \stdClass>}
     */
    public function searchForumPosts(string $keywords, int $minClass, int $offset, int $perPage): array
    {
        return $this->postService->searchForumPosts($keywords, $minClass, $offset, $perPage);
    }

    public function getForumTodayPostCount(int $forumid, string $todayDate): int
    {
        return $this->postService->getForumTodayPostCount($forumid, $todayDate);
    }
}
