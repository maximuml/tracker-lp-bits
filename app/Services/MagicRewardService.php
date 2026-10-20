<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\BusinessType;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Repositories\RewardRepository;
use App\Repositories\TorrentDetailRepository;
use App\Support\Bonus;
use Illuminate\Support\Facades\DB;

/**
 * The magic-award pipeline shared by magic.php and the Livewire
 * MagicSection::give action — extracted from
 * BonusHistoryController::magic so both entry points validate and
 * reward identically.
 */
final class MagicRewardService
{
    public function __construct(
        private readonly TorrentDetailRepository $torrentDetailRepository,
        private readonly RewardRepository $rewardRepository,
        private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly BonusRepository $bonusRepository,
    ) {}

    /**
     * Give a magic-award value from the current user to the torrent owner.
     *
     * @throws \LogicException When the value/owner/limit checks fail.
     */
    public function give(User $user, int $torrentId, int $value): void
    {
        $userId = (int) $user->id;
        $value = (int) abs($value);

        if (! in_array($value, Setting::getBonusRewardOptions())) {
            throw new \LogicException('Invalid value.');
        }

        $torrentOwner = $this->torrentRepository->getOwnerId($torrentId);
        if (! $torrentOwner) {
            throw new \LogicException('Invalid torrent id!');
        }
        if ((int) $torrentOwner === $userId) {
            throw new \LogicException('You are giving magic to yourself.');
        }

        DB::transaction(function () use ($userId, $torrentId, $value, $torrentOwner): void {
            // Lock both balance rows in canonical id order — serializes
            // concurrent gives from the same user and avoids ABBA
            // deadlocks when two users reward each other's torrents.
            $firstId = min($userId, (int) $torrentOwner);
            $secondId = max($userId, (int) $torrentOwner);
            $firstBonus = DB::table('users')->where('id', $firstId)->lockForUpdate()->value('seedbonus');
            $secondBonus = DB::table('users')->where('id', $secondId)->lockForUpdate()->value('seedbonus');
            $senderBonus = $firstId === $userId ? $firstBonus : $secondBonus;
            $ownerBonus = $firstId === $userId ? $secondBonus : $firstBonus;

            if ($senderBonus === null || $value > (float) $senderBonus) {
                throw new \LogicException('You do not have such bonus!');
            }
            if ($ownerBonus === null) {
                throw new \LogicException('Invalid torrent owner!');
            }

            if ($this->torrentDetailRepository->hasMagicRecord($torrentId, $userId)) {
                throw new \LogicException('You already gave the magic value!');
            }

            $todayCount = $this->rewardRepository->countSince($userId, now()->startOfDay());
            $timesLimit = Setting::getBonusRewardTimesLimit();
            if ($timesLimit > 0 && $todayCount >= $timesLimit) {
                throw new \LogicException('You already reach times limit!');
            }

            $senderBonus = (float) $senderBonus;
            $ownerBonus = (float) $ownerBonus;

            $this->torrentDetailRepository->insertMagic($torrentId, $userId, $value);

            Bonus::updatePoints('-', (float) $value, $userId);
            $this->bonusRepository->add($userId, $senderBonus, $value, $senderBonus - $value, '', BusinessType::REWARD_TORRENT->value);

            Bonus::updatePoints('+', (float) $value, (int) $torrentOwner);
            $this->bonusRepository->add((int) $torrentOwner, $ownerBonus, $value, $ownerBonus + $value, '', BusinessType::TORRENT_BE_REWARD->value);
        });
    }
}
