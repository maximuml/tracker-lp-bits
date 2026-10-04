<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Repositories\CommentRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Log;

/**
 * Priority Class 3: offer pruning.
 */
final class OfferCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly OfferVoteRepositoryInterface $offerVotes,
        private readonly OfferRepositoryInterface $offers,
        private readonly CommentRepository $comments,
    ) {}

    /**
     * Priority Class 3: delete offers that were never voted on and offers that
     * were approved but never uploaded.
     */
    public function pruneOffers(): string
    {
        $offerVoteTimeout = (int) SiteConfig::current()->main->offerVoteTimeout(259200);
        if ($offerVoteTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerVoteTimeout);
            $offerIds = $this->offers->pluckNotAllowedAddedBefore($dt);

            $this->deleteOffers($offerIds, 'vote timeout');
        }

        $offerUploadTimeout = (int) SiteConfig::current()->main->offerUploadTimeout(86400);
        if ($offerUploadTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerUploadTimeout);
            $offerIds = $this->offers->pluckAllowedBefore($dt);

            $this->deleteOffers($offerIds, 'upload timeout');
        }

        return 'delete offers if not voted on / uploaded after some time';
    }

    // ------------------------------------------------------------------------
    // Offer helpers
    // ------------------------------------------------------------------------

    /**
     * @param  array<string, int>  $offerIds
     */
    private function deleteOffers(array $offerIds, string $reason): void
    {
        if ($offerIds === []) {
            return;
        }

        $ids = array_values($offerIds);

        $this->offerVotes->deleteVotesForOffers($ids);
        $this->comments->deleteForOffers($ids);
        $this->offers->deleteMany($ids);

        foreach ($offerIds as $name => $id) {
            Log::write("Offer {$id} ({$name}) was deleted by system ({$reason})", 'normal');
        }
    }

    public function run(): string
    {
        return $this->pruneOffers();
    }
}
