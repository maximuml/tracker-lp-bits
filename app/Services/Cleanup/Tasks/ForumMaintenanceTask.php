<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Cache\LegacyRedisCache;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 3: recompute forum post/topic counts.
 */
final class ForumMaintenanceTask implements CleanupTask
{
    /**
     * Priority Class 3: recompute post/topic counts for every forum.
     */
    public function updateForumCounts(): string
    {
        $forumIds = DB::table('forums')->pluck('id');

        // Get all topics with their forumid in a single query
        $topics = DB::table('topics')->whereIn('forumid', $forumIds)->pluck('forumid', 'id');

        // Batch count posts per topic in a single grouped query
        $postCounts = DB::table('posts')
            ->select('topicid', DB::raw('COUNT(*) as cnt'))
            ->whereIn('topicid', $topics->keys()->all())
            ->groupBy('topicid')
            ->get()
            ->keyBy('topicid');

        // Compute per-forum totals
        $forumPostCounts = [];
        $forumTopicCounts = [];
        foreach ($forumIds as $forumId) {
            $forumPostCounts[$forumId] = 0;
            $forumTopicCounts[$forumId] = 0;
        }

        foreach ($topics as $topicId => $forumId) {
            $postCount = $postCounts->get($topicId);
            if ($postCount !== null) {
                $forumPostCounts[$forumId] += (int) $postCount->cnt;
            }
            $forumTopicCounts[$forumId]++;
        }

        // Batch forum updates in a single transaction
        DB::transaction(function () use ($forumPostCounts, $forumTopicCounts): void {
            foreach ($forumPostCounts as $forumId => $postcount) {
                DB::table('forums')
                    ->where('id', $forumId)
                    ->update(['postcount' => $postcount, 'topiccount' => $forumTopicCounts[$forumId]]);
            }
        });

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('forums_list');
        }

        return 'update forum post/topic count';
    }

    public function run(): string
    {
        return $this->updateForumCounts();
    }
}
