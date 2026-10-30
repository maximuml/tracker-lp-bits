<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessType;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\UserAccountRepository;
use App\Support\Config\SiteConfig;
use Illuminate\Support\Facades\DB;

/**
 * Handles the "thank" action on a torrent: recording the thank and
 * granting bonus points to both the thanker and the torrent owner.
 */
final class ThankService
{
    public function __construct(
        private readonly UserAccountRepository $userAccountRepository,
        private readonly BonusLedgerService $bonusLedger,
    ) {}

    /**
     * Record a thank and grant bonus points inside a transaction.
     *
     * @throws \LogicException If the user already thanked or thanks themselves.
     * @throws \RuntimeException If the bonus increment fails (concurrent update).
     */
    public function thankTorrent(User $user, Torrent $torrent): Thank
    {
        $torrentOwner = $this->userAccountRepository->findOrFailById((int) $torrent->owner);
        if ($user->id == $torrentOwner->id) {
            throw new \LogicException("you can't thank to yourself");
        }
        $torrentOwner->checkIsNormal();
        // Pre-check for existing thank to provide a clean error message.
        // The unique constraint on (torrentid, userid) provides the
        // definitive race-condition protection at the database level.
        if ($user->thank_torrent_logs()->where('torrentid', $torrent->id)->exists()) {
            throw new \LogicException('you already thank this torrent');
        }

        return DB::transaction(function () use ($user, $torrentOwner, $torrent) {
            $thank = $user->thank_torrent_logs()->create(['torrentid' => $torrent->id]);

            $sayThanksBonus = SiteConfig::current()->bonus->sayThanks();
            $receiveThanksBonus = SiteConfig::current()->bonus->receiveThanks();

            if ($sayThanksBonus > 0) {
                $this->bonusLedger->credit(
                    (int) $user->id,
                    (float) $sayThanksBonus,
                    BusinessType::SAY_THANKS,
                    "torrent #{$torrent->id}"
                );
            }
            if ($receiveThanksBonus > 0) {
                $this->bonusLedger->credit(
                    (int) $torrentOwner->id,
                    (float) $receiveThanksBonus,
                    BusinessType::RECEIVE_THANKS,
                    "torrent #{$torrent->id}"
                );
            }

            return $thank;
        });
    }
}
