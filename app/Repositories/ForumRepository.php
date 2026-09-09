<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Forum;
use App\Models\ForumMod;
use App\Models\User;
use App\Support\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ForumRepository extends BaseRepository
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
}
