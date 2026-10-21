<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\BusinessType;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\RewardRepository;
use App\Repositories\TorrentDetailRepository;
use Illuminate\Support\Facades\DB;

/**
 * The magic-award pipeline shared by magic.php, the Livewire
 * MagicSection::give action and the API `POST /rewards` endpoint —
 * all entry points validate, lock, transfer and log identically.
 */
final class MagicRewardService
{
    public function __construct(
        private readonly TorrentDetailRepository $torrentDetailRepository,
        private readonly RewardRepository $rewardRepository,
        private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly BonusLedgerService $ledger,
    ) {}

    /**
     * Give a magic-award value from the current user to the torrent owner.
     *
     * @throws \LogicException When the value/owner/limit checks fail.
     */
    public function give(User $user, int $torrentId, int $value): Reward
    {
        $userId = (int) $user->id;
        $value = (int) abs($value);

        if (! in_array($value, Setting::getBonusRewardOptions())) {
            throw new \LogicException('Invalid value.');
        }

        $torrent = Torrent::query()->find($torrentId);
        if ($torrent === null) {
            throw new \LogicException('Invalid torrent id!');
        }
        $torrent->checkIsNormal();
        $torrentOwner = $this->torrentRepository->getOwnerId($torrentId);
        if (! $torrentOwner) {
            throw new \LogicException('Invalid torrent id!');
        }
        if ((int) $torrentOwner === $userId) {
            throw new \LogicException('You are giving magic to yourself.');
        }
        User::query()->findOrFail((int) $torrentOwner)->checkIsNormal();
        // Fast-path balance check so the sender gets the friendly
        // message; the authoritative check runs under the row lock in
        // BonusLedgerService::transfer.
        if ($value > (float) $user->seedbonus) {
            throw new \LogicException('You do not have such bonus!');
        }

        return DB::transaction(function () use ($userId, $torrentId, $value, $torrentOwner) {
            if ($this->torrentDetailRepository->hasMagicRecord($torrentId, $userId)) {
                throw new \LogicException('You already gave the magic value!');
            }

            $todayCount = $this->rewardRepository->countSince($userId, now()->startOfDay());
            $timesLimit = Setting::getBonusRewardTimesLimit();
            if ($timesLimit > 0 && $todayCount >= $timesLimit) {
                throw new \LogicException('You already reach times limit!');
            }

            // Lock both balance rows in canonical id order — serializes
            // concurrent gives from the same user and avoids ABBA
            // deadlocks when two users reward each other's torrents.
            $this->ledger->transfer(
                $userId,
                (int) $torrentOwner,
                (float) $value,
                BusinessType::REWARD_TORRENT,
                BusinessType::TORRENT_BE_REWARD,
            );

            $magicId = $this->torrentDetailRepository->insertMagic($torrentId, $userId, $value);

            return Reward::query()->findOrFail($magicId);
        });
    }
}
