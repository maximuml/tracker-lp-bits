<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ForumMod;
use App\Support\Cache;
use Illuminate\Support\Facades\DB;

class ForumModRepository extends BaseRepository
{
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

    public function isModeratorOfForum(int $forumId, int $userId): bool
    {
        return ForumMod::query()
            ->where('forumid', $forumId)
            ->where('userid', $userId)
            ->exists();
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

    private function clearModeratorCache(): void
    {
        Cache::forgetWithLocales('forum_moderator_array');
    }
}
