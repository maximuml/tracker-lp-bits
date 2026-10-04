<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Repositories\ModerationRepository;
use App\Repositories\TopicReadStateRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Services\Cleanup\HousekeepingRepositories;

/**
 * Priority Class 5: cleanup tasks that run every 15 days.
 */
final class PeriodicHousekeepingTask implements CleanupTask
{
    public function __construct(
        private readonly ShoutboxRepositoryInterface $shoutbox,
        private readonly TopicReadStateRepository $topicReadState,
        private readonly ModerationRepository $moderation,
        private readonly HousekeepingRepositories $repos,
    ) {}

    /**
     * Priority Class 5: cleanup tasks that run every 15 days.
     */
    public function cleanupClass5(): string
    {
        $this->updateClientPopularity();
        $this->deleteOldSystemMessages();
        $this->deleteOldReadPosts();
        $this->deleteOldCheaters();
        $this->deleteOldShoutbox();
        $this->deleteOldSiteLog();
        $this->lockOldTopics();
        $this->deleteOldReports();

        return 'cleanup class 5';
    }

    // ------------------------------------------------------------------------
    // Class 5 helpers
    // ------------------------------------------------------------------------

    private function updateClientPopularity(): void
    {
        $clientIds = $this->repos->agentAllow->listClientIds();

        foreach ($clientIds as $clientId) {
            $count = $this->repos->userCleanup->countByClientselect((int) $clientId);
            $this->repos->agentAllow->setHits((int) $clientId, $count);
        }
    }

    private function deleteOldSystemMessages(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->repos->messages->deleteOldSystemMessages($until);
    }

    private function deleteOldReadPosts(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $postId = $this->repos->posts->findLastIdAddedBefore($until);

        if ($postId) {
            $this->repos->userCleanup->updateLastCatchupBelow($postId);
            $this->topicReadState->deleteWithLastPostReadBefore($postId);
        }
    }

    private function deleteOldCheaters(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->repos->logs->deleteOldCheaters($until);
    }

    private function deleteOldShoutbox(): void
    {
        $length = 180 * 86400;
        $until = time() - $length;

        $this->shoutbox->deleteBefore($until);
    }

    private function deleteOldSiteLog(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->repos->siteLogs->deleteBefore($until);
    }

    private function lockOldTopics(): void
    {
        $length = 365 * 86400;
        $diff = time() - $length;
        $this->repos->topics->lockNonStickyTopicsWithLastPostBefore($diff);
    }

    private function deleteOldReports(): void
    {
        $length = 4 * 7 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $this->moderation->deleteDealtWithReportsBefore($until);
    }

    public function run(): string
    {
        return $this->cleanupClass5();
    }
}
