<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\BusinessType;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Repositories\RewardRepository;
use App\Repositories\TorrentDetailRepository;
use App\Support\Bonus;

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
        private readonly UserRepositoryInterface $userRepository,
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
        if ($value > (float) $user->seedbonus) {
            throw new \LogicException('You do not have such bonus!');
        }

        $torrentOwner = $this->torrentRepository->getOwnerId($torrentId);
        if (! $torrentOwner) {
            throw new \LogicException('Invalid torrent id!');
        }
        if ((int) $torrentOwner === $userId) {
            throw new \LogicException('You are giving magic to yourself.');
        }

        if ($this->torrentDetailRepository->hasMagicRecord($torrentId, $userId)) {
            throw new \LogicException('You already gave the magic value!');
        }

        $todayStr = now()->startOfDay();
        $todayCount = $this->rewardRepository->countSince($userId, $todayStr);
        $timesLimit = Setting::getBonusRewardTimesLimit();
        if ($timesLimit > 0 && $todayCount >= $timesLimit) {
            throw new \LogicException('You already reach times limit!');
        }

        $torrentOwnerInfo = $this->userRepository->findById((int) $torrentOwner, User::$commonFields);
        if (! $torrentOwnerInfo) {
            throw new \LogicException('Invalid torrent owner!');
        }

        $this->torrentDetailRepository->insertMagic($torrentId, $userId, $value);

        Bonus::updatePoints('-', (float) $value, $userId);
        $this->bonusRepository->add($userId, (float) $user->seedbonus, $value, (float) $user->seedbonus - $value, '', BusinessType::REWARD_TORRENT->value);

        Bonus::updatePoints('+', (float) $value, (int) $torrentOwner);
        $this->bonusRepository->add((int) $torrentOwnerInfo['id'], (float) $torrentOwnerInfo['seedbonus'], $value, (float) $torrentOwnerInfo['seedbonus'] + $value, '', BusinessType::TORRENT_BE_REWARD->value);
    }
}
