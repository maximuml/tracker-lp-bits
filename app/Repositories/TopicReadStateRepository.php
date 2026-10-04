<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Topic read-state repository: tracks which posts a user has read per topic.
 */
class TopicReadStateRepository extends BaseRepository
{
    /**
     * @return array<int, int>|null
     */
    public function getLastReadPosts(int $userId): ?array
    {
        $rows = DB::table('readposts')->where('userid', $userId)->get(['topicid', 'lastpostread']);

        if ($rows->isEmpty()) {
            return null;
        }

        $ret = [];
        foreach ($rows as $row) {
            $ret[(int) $row->topicid] = (int) $row->lastpostread;
        }

        return $ret;
    }

    public function markPostRead(int $userId, int $topicId, int $postId, int $lastCatchup): bool
    {
        $readPost = DB::table('readposts')
            ->where('userid', $userId)
            ->where('topicid', $topicId)
            ->first();

        if (! $readPost) {
            return (bool) DB::table('readposts')->insert([
                'userid' => $userId,
                'topicid' => $topicId,
                'lastpostread' => $postId,
            ]);
        }

        if ($lastCatchup < $postId) {
            return (bool) DB::table('readposts')
                ->where('userid', $userId)
                ->where('topicid', $topicId)
                ->update(['lastpostread' => $postId]);
        }

        return true;
    }

    public function clearReadPosts(int $userId): void
    {
        DB::table('readposts')->where('userid', $userId)->delete();
    }

    public function deleteWithLastPostReadBefore(int $lastPostId): int
    {
        return DB::table('readposts')->where('lastpostread', '<', $lastPostId)->delete();
    }
}
