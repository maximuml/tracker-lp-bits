<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Database;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 5: cleanup tasks that run every 15 days.
 */
final class PeriodicHousekeepingTask implements CleanupTask
{
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
        $clientIds = DB::table('agent_allowed_family')->pluck('id');

        foreach ($clientIds as $clientId) {
            $count = DB::table('users')->where('clientselect', $clientId)->count();
            DB::table('agent_allowed_family')->where('id', $clientId)->update(['hits' => $count]);
        }
    }

    private function deleteOldSystemMessages(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('messages')->whereNull('sender')->where('added', '<', $until)->delete();
    }

    private function deleteOldReadPosts(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $postId = DB::table('posts')
            ->where('added', '<', $until)
            ->orderBy('added', 'desc')
            ->value('id');

        if ($postId) {
            DB::table('users')->where('last_catchup', '<', $postId)->update(['last_catchup' => $postId]);
            DB::table('readposts')->where('lastpostread', '<', $postId)->delete();
        }
    }

    private function deleteOldCheaters(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('cheaters')->where('added', '<', $until)->delete();
    }

    private function deleteOldShoutbox(): void
    {
        $length = 180 * 86400;
        $until = time() - $length;

        DB::table('shoutbox')->where('date', '<', $until)->delete();
    }

    private function deleteOldSiteLog(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('sitelog')->where('added', '<', $until)->delete();
    }

    private function lockOldTopics(): void
    {
        $length = 365 * 86400;
        $diff = time() - $length;
        $postAddedField = Database::unixTimestampField('posts.added');

        DB::table('topics')
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

        DB::table('reports')
            ->where('dealtwith', 1)
            ->where('added', '<', $until)
            ->delete();
    }

    public function run(): string
    {
        return $this->cleanupClass5();
    }
}
