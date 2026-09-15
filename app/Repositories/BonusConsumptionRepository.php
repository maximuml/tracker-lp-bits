<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Models\BonusLogs;
use App\Models\User;
use App\Support\Cache;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;

/**
 * Core bonus consumption mutation: lock-for-update balance decrement,
 * bonus log write and user cache invalidation.
 *
 * Extracted from BonusRepository to keep both classes under the
 * 400-line ratchet.
 */
class BonusConsumptionRepository extends BaseRepository
{
    /**
     * @param  User|int  $user
     * @param  array<string, mixed>  $userUpdates
     * @return void
     */
    public function consumeUserBonus($user, float $requireBonus, int $logBusinessType, string $logComment = '', array $userUpdates = [])
    {
        $logBusinessTypeEnum = BusinessType::fromIntSafe($logBusinessType);
        if ($logBusinessTypeEnum === null) {
            throw new \InvalidArgumentException("Invalid logBusinessType: $logBusinessType");
        }
        if (isset($userUpdates['seedbonus']) || isset($userUpdates['bonuscomment']) || isset($userUpdates['modcomment'])) {
            throw new \InvalidArgumentException('Not support update seedbonus or bonuscomment or modcomment');
        }
        if ($requireBonus <= 0) {
            return;
        }
        $user = $this->getUser($user);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found');
        }
        $userId = (int) $user->id;
        $passkey = (string) $user->passkey;
        DB::transaction(function () use ($userId, $passkey, $requireBonus, $logBusinessType, $logBusinessTypeEnum, $logComment, $userUpdates) {
            // Lock the user row for update to prevent concurrent bonus consumption
            $lockedUser = DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null) {
                throw new \InvalidArgumentException('User not found');
            }

            $oldUserBonus = (float) ($lockedUser->seedbonus ?? 0);
            if ($oldUserBonus < $requireBonus) {
                Logger::writeWithContext((string) "user: {$userId}, bonus: {$oldUserBonus} < requireBonus: {$requireBonus}", (string) 'error', (bool) false);
                throw new \LogicException('User bonus not enough.');
            }

            $newUserBonus = bcsub((string) $oldUserBonus, (string) $requireBonus);
            $log = "user: {$userId}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
            $userUpdates['seedbonus'] = $newUserBonus;
            $affectedRows = DB::table('users')
                ->where('id', $userId)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ('update user seedbonus affected rows: '.$affectedRows.' != 1, query: '.LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                throw new \RuntimeException('Update user seedbonus fail.');
            }
            $nowStr = now()->toDateTimeString();
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $userId,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', $logBusinessTypeEnum->label(), $logComment),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            BonusLogs::query()->insert($bonusLog);
            Logger::writeWithContext((string) ('bonusLog: '.Json::encode($bonusLog)), (string) 'info', (bool) false);
            Cache::clearUser($userId, $passkey);
        });
    }

    /**
     * Consume bonus and atomically increment the user's charity field.
     *
     * @param  User|int  $user
     */
    public function consumeUserBonusAndIncrementCharity($user, float $requireBonus, int $logBusinessType, string $logComment, float $charityIncrement): void
    {
        $this->consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment, [
            'charity' => DB::raw(DB::getQueryGrammar()->wrap('charity').' + '.(float) $charityIncrement), // @phpstan-ignore argument.type
        ]);
    }
}
