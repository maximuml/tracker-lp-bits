<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Models\Forum;
use App\Models\User;
use App\Support\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForumRepository extends BaseRepository implements ForumRepositoryInterface
{
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

    public function clearForumCache(): void
    {
        Cache::forgetWithLocales('forums_list');
        Cache::forgetWithLocales('forum_moderator_array');
    }

    /** @return  array<string, mixed>|null */
    public function getForumRow(int $id): ?array
    {
        $row = (array) DB::table('forums')->where('id', $id)->first();

        return empty($row) ? null : $row;
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

    public function getForumOrFail(int $id): Forum
    {
        return Forum::query()->findOrFail($id);
    }

    public function getForumMinclasswrite(int $forumid): ?int
    {
        $forum = Forum::query()->where('id', $forumid)->first(['minclasswrite']);

        return $forum ? (int) $forum->minclasswrite : null;
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

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Forum>
     */
    public function listOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        return Forum::query()->orderBy('sort')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Forum
    {
        return Forum::query()->create($data);
    }

    /**
     * @return Collection<int, int>
     */
    public function listIds(): Collection
    {
        return Forum::query()->pluck('id');
    }

    public function updateCounts(int $forumId, int $postcount, int $topiccount): int
    {
        return Forum::query()
            ->where('id', $forumId)
            ->update(['postcount' => $postcount, 'topiccount' => $topiccount]);
    }
}
