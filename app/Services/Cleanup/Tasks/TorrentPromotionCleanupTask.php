<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\ModelEvent;
use App\Enums\PromotionTimeType;
use App\Enums\TorrentPromotion;
use App\Repositories\TorrentCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Log;

/**
 * Priority Class 3: expire time-based torrent promotions.
 */
final class TorrentPromotionCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly TorrentCleanupRepository $torrentCleanup,
    ) {}

    /**
     * Priority Class 3: expire time-based global torrent promotions.
     */
    public function expireTorrentPromotions(): string
    {
        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireHalfleech(0),
            TorrentPromotion::HALF_DOWN->value,
            (int) SiteConfig::current()->torrent->halfleechbecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireFree(0),
            TorrentPromotion::FREE->value,
            (int) SiteConfig::current()->torrent->freebecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwoup(0),
            TorrentPromotion::TWO_TIMES_UP->value,
            (int) SiteConfig::current()->torrent->twoupbecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwoupfree(0),
            TorrentPromotion::FREE_TWO_TIMES_UP->value,
            (int) SiteConfig::current()->torrent->twoupfreebecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwouphalfleech(0),
            TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value,
            (int) SiteConfig::current()->torrent->twouphalfleechbecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireThirtypercentleech(0),
            TorrentPromotion::ONE_THIRD_DOWN->value,
            (int) SiteConfig::current()->torrent->thirtypercentleechbecome(TorrentPromotion::NORMAL->value),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireNormal(0),
            TorrentPromotion::NORMAL->value,
            (int) SiteConfig::current()->torrent->normalbecome(TorrentPromotion::NORMAL->value),
        );

        $this->expireIndividualPromotions();

        return 'expire torrent promotion';
    }

    // ------------------------------------------------------------------------
    // Torrent promotion helpers
    // ------------------------------------------------------------------------

    private function expirePromotionType(int $days, int $fromState, int $toState): void
    {
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        $validStates = [
            TorrentPromotion::NORMAL->value,
            TorrentPromotion::FREE->value,
            TorrentPromotion::TWO_TIMES_UP->value,
            TorrentPromotion::FREE_TWO_TIMES_UP->value,
            TorrentPromotion::HALF_DOWN->value,
            TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value,
        ];
        $targetState = in_array($toState, $validStates, true) ? $toState : TorrentPromotion::NORMAL->value;

        $becomeMap = [
            TorrentPromotion::NORMAL->value => 'normal',
            TorrentPromotion::FREE->value => 'Free',
            TorrentPromotion::TWO_TIMES_UP->value => '2X',
            TorrentPromotion::FREE_TWO_TIMES_UP->value => '2X Free',
            TorrentPromotion::HALF_DOWN->value => '50%',
            TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value => '2X 50%',
        ];
        $become = $becomeMap[$targetState];

        $torrents = $this->torrentCleanup->getExpiredGlobalPromotions($dt, $fromState, PromotionTimeType::GLOBAL->value);

        if ($torrents->isNotEmpty()) {
            $this->torrentCleanup->setPromotionStateForIds($torrents->pluck('id')->all(), $targetState);
        }

        foreach ($torrents as $torrent) {
            $arr = (array) $torrent;

            Events::publishModel(ModelEvent::TorrentUpdated, (int) $arr['id']);

            if ($targetState === TorrentPromotion::NORMAL->value) {
                Log::write("Torrent {$arr['id']} ({$arr['name']}) is no longer on promotion (time expired)", 'normal');
            } else {
                Log::write("Promotion type for torrent {$arr['id']} ({$arr['name']}) is changed to {$become} (time expired)", 'normal');
            }
        }
    }

    private function expireIndividualPromotions(): void
    {
        $torrents = $this->torrentCleanup->listExpiredDeadlinePromotions(PromotionTimeType::DEADLINE->value);

        if ($torrents->isNotEmpty()) {
            $this->torrentCleanup->resetDeadlinePromotions(
                $torrents->pluck('id')->all(),
                TorrentPromotion::NORMAL->value,
                PromotionTimeType::GLOBAL->value,
            );
        }

        foreach ($torrents as $torrent) {
            Events::publishModel(ModelEvent::TorrentUpdated, $torrent->id);
        }
    }

    public function run(): string
    {
        return $this->expireTorrentPromotions();
    }
}
