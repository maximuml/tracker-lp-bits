<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TorrentBuyLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BonusRepository extends BaseRepository
{
    public function __construct(
        private readonly BonusPurchaseRepository $purchaseRepository,
        private readonly BonusConsumptionRepository $consumptionRepository,
    ) {}

    /**
     * @return int number of affected rows
     */
    public function incrementSeedbonusForLowRatioReceivers(float $ratioCharity, float $amount): int
    {
        return User::query()
            ->where('enabled', true)
            ->where('downloaded', '>', 10737418240)
            ->whereRaw('? > uploaded/downloaded', [$ratioCharity])
            ->increment('seedbonus', $amount);
    }

    public function incrementUserSeedbonus(int $userId, float $amount): bool
    {
        return (bool) User::query()->where('id', $userId)->increment('seedbonus', $amount);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $hitAndRunId
     */
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        return $this->purchaseRepository->consumeToCancelHitAndRun($uid, $hitAndRunId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $medalId
     */
    public function consumeToBuyMedal($uid, $medalId): bool
    {
        return $this->purchaseRepository->consumeToBuyMedal($uid, $medalId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $medalId
     * @param  mixed  $toUid
     */
    public function consumeToGiftMedal($uid, $medalId, $toUid): bool
    {
        return $this->purchaseRepository->consumeToGiftMedal($uid, $medalId, $toUid);
    }

    /**
     * @param  mixed  $uid
     */
    public function consumeToBuyAttendanceCard($uid): bool
    {
        return $this->purchaseRepository->consumeToBuyAttendanceCard($uid);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $count
     */
    public function consumeToBuyTemporaryInvite($uid, $count = 1): bool
    {
        return $this->purchaseRepository->consumeToBuyTemporaryInvite($uid, $count);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $duration
     */
    public function consumeToBuyRainbowId($uid, $duration = 30): bool
    {
        return $this->purchaseRepository->consumeToBuyRainbowId($uid, $duration);
    }

    /**
     * @param  mixed  $uid
     */
    public function consumeToBuyChangeUsernameCard($uid): bool
    {
        return $this->purchaseRepository->consumeToBuyChangeUsernameCard($uid);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $channel
     */
    public function consumeToBuyTorrent($uid, $torrentId, $channel = 'Web'): TorrentBuyLog
    {
        return $this->purchaseRepository->consumeToBuyTorrent($uid, $torrentId, $channel);
    }

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $userUpdates
     */
    public function consumeUserBonus($user, float $requireBonus, int $logBusinessType, string $logComment = '', array $userUpdates = []): void
    {
        $this->consumptionRepository->consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment, $userUpdates);
    }

    /**
     * Consume bonus and atomically increment the user's charity field.
     *
     * @param  User|int  $user
     */
    public function consumeUserBonusAndIncrementCharity($user, float $requireBonus, int $logBusinessType, string $logComment, float $charityIncrement): void
    {
        $this->consumptionRepository->consumeUserBonusAndIncrementCharity($user, $requireBonus, $logBusinessType, $logComment, $charityIncrement);
    }

    public function updateSeedBonus(string $op, float $point, int|string $id): void
    {
        if (! in_array($op, ['+', '-'], true)) {
            throw new \InvalidArgumentException('Invalid seedbonus operation: '.$op);
        }
        DB::table('users')
            ->where('id', $id)
            ->update([
                'seedbonus' => DB::raw(DB::getQueryGrammar()->wrap('seedbonus').' '.$op.' '.(float) $point), // @phpstan-ignore argument.type
            ]);
    }
}
