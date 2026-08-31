<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Log;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 3: offer pruning.
 */
final class OfferCleanupTask implements CleanupTask
{
    /**
     * Priority Class 3: delete offers that were never voted on and offers that
     * were approved but never uploaded.
     */
    public function pruneOffers(): string
    {
        $offerVoteTimeout = (int) SiteConfig::current()->main->offerVoteTimeout(259200);
        if ($offerVoteTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerVoteTimeout);
            $offerIds = DB::table('offers')
                ->where('added', '<', $dt)
                ->where('allowed', '<>', 'allowed')
                ->pluck('id', 'name')
                ->all();

            $this->deleteOffers($offerIds, 'vote timeout');
        }

        $offerUploadTimeout = (int) SiteConfig::current()->main->offerUploadTimeout(86400);
        if ($offerUploadTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerUploadTimeout);
            $offerIds = DB::table('offers')
                ->where('allowedtime', '<', $dt)
                ->where('allowed', 'allowed')
                ->pluck('id', 'name')
                ->all();

            $this->deleteOffers($offerIds, 'upload timeout');
        }

        return 'delete offers if not voted on / uploaded after some time';
    }

    // ------------------------------------------------------------------------
    // Offer helpers
    // ------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $offerIds
     */
    private function deleteOffers(array $offerIds, string $reason): void
    {
        if ($offerIds === []) {
            return;
        }

        $ids = array_values($offerIds);

        DB::table('offervotes')->whereIn('offerid', $ids)->delete();
        DB::table('comments')->whereIn('offer', $ids)->delete();
        DB::table('offers')->whereIn('id', $ids)->delete();

        foreach ($offerIds as $name => $id) {
            Log::write("Offer {$id} ({$name}) was deleted by system ({$reason})", 'normal');
        }
    }

    public function run(): string
    {
        return $this->pruneOffers();
    }
}
