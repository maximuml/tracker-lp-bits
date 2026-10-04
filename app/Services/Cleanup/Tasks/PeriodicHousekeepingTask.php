<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Models\AgentAllow;
use App\Models\Cheater;
use App\Models\Message;
use App\Models\Post;
use App\Models\SiteLog;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ModerationRepository;
use App\Repositories\TopicReadStateRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Database;

/**
 * Priority Class 5: cleanup tasks that run every 15 days.
 */
final class PeriodicHousekeepingTask implements CleanupTask
{
    public function __construct(
        private readonly ShoutboxRepositoryInterface $shoutbox,
        private readonly TopicReadStateRepository $topicReadState,
        private readonly ModerationRepository $moderation,
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
        $clientIds = AgentAllow::query()->pluck('id');

        foreach ($clientIds as $clientId) {
            $count = User::query()->where('clientselect', $clientId)->count();
            AgentAllow::query()->where('id', $clientId)->update(['hits' => $count]);
        }
    }

    private function deleteOldSystemMessages(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        Message::query()->whereNull('sender')->where('added', '<', $until)->delete();
    }

    private function deleteOldReadPosts(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $postId = Post::query()
            ->where('added', '<', $until)
            ->orderBy('added', 'desc')
            ->value('id');

        if ($postId) {
            User::query()->where('last_catchup', '<', $postId)->update(['last_catchup' => $postId]);
            $this->topicReadState->deleteWithLastPostReadBefore((int) $postId);
        }
    }

    private function deleteOldCheaters(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        Cheater::query()->where('added', '<', $until)->delete();
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

        SiteLog::query()->where('added', '<', $until)->delete();
    }

    private function lockOldTopics(): void
    {
        $length = 365 * 86400;
        $diff = time() - $length;
        $postAddedField = Database::unixTimestampField('posts.added');

        Topic::query()
            ->where('sticky', false)
            ->whereIn('lastpost', function ($query) use ($postAddedField, $diff): void {
                $query->select('id')->from('posts')->whereRaw("{$postAddedField} < ?", [$diff]);
            })
            ->update(['locked' => true]);
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
