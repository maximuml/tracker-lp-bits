<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Repositories\PostRepository;
use App\Repositories\TopicMaintenanceRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Cache\NexusCache;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 3: recompute forum post/topic counts.
 */
final class ForumMaintenanceTask implements CleanupTask
{
    public function __construct(
        private readonly ForumRepositoryInterface $forums,
        private readonly TopicMaintenanceRepository $topics,
        private readonly PostRepository $posts,
        private readonly ?NexusCache $cache = null,
    ) {}

    /**
     * Priority Class 3: recompute post/topic counts for every forum.
     */
    public function updateForumCounts(): string
    {
        $forumIds = $this->forums->listIds();

        // Get all topics with their forumid in a single query
        $topics = $this->topics->listForumIdById($forumIds);

        // Batch count posts per topic in a single grouped query
        $postCounts = $this->posts->countTopicPostsBatch($topics->keys()->all());

        // Compute per-forum totals
        $forumPostCounts = [];
        $forumTopicCounts = [];
        foreach ($forumIds as $forumId) {
            $forumPostCounts[$forumId] = 0;
            $forumTopicCounts[$forumId] = 0;
        }

        foreach ($topics as $topicId => $forumId) {
            $postCount = $postCounts[$topicId] ?? 0;
            $forumPostCounts[$forumId] += $postCount;
            $forumTopicCounts[$forumId]++;
        }

        // Batch forum updates in a single transaction
        DB::transaction(function () use ($forumPostCounts, $forumTopicCounts): void {
            foreach ($forumPostCounts as $forumId => $postcount) {
                $this->forums->updateCounts($forumId, $postcount, $forumTopicCounts[$forumId]);
            }
        });

        if ($this->cache !== null) {
            $this->cache->forget('forums_list');
        }

        return 'update forum post/topic count';
    }

    public function run(): string
    {
        return $this->updateForumCounts();
    }
}
