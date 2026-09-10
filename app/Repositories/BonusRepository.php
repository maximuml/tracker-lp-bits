<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Enums\HitAndRunStatus;
use App\Enums\UserMedalStatus;
use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\OutboxService;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BonusRepository extends BaseRepository
{
    public function __construct(
        private readonly OutboxService $outboxService,
        private readonly MedalRepository $medalRepository,
        private readonly ToolRepository $toolRepository,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * @param  mixed  $uid
     * @param  mixed  $hitAndRunId
     */
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (! HitAndRun::getIsEnabled()) {
            throw new \LogicException('H&R not enabled.');
        }
        $user = User::query()->findOrFail((int) $uid);
        $hitAndRun = HitAndRun::query()->findOrFail((int) $hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        if ($hitAndRun->status == HitAndRunStatus::PARDONED->value) {
            throw new \LogicException("H&R: $hitAndRunId already pardoned.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        DB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = Locale::trans('hr.bonus_cancel_comment', ['bonus' => $requireBonus], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);

            $this->consumeUserBonus($user, $requireBonus, BusinessType::CANCEL_HIT_AND_RUN->value, "$comment(H&R ID: {$hitAndRun->id})");

            $existingComment = (string) $hitAndRun->comment;
            $newComment = $existingComment === '' ? $comment : $comment."\n".$existingComment;
            $hitAndRun->update([
                'status' => HitAndRunStatus::PARDONED->value,
                'comment' => $newComment,
            ]);
        });

        return true;

    }

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
     * @param  mixed  $medalId
     */
    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail((int) $uid);
        $medal = Medal::query()->findOrFail((int) $medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        Logger::writeWithContext((string) LegacyDb::lastQuery(false, 'json'), (string) 'info', (bool) false);
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $requireBonus = $medal->price;
        DB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = Locale::trans('bonus.comment_buy_medal', ['bonus' => $requireBonus, 'medal_name' => $medal->name], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_MEDAL->value, "$comment(medal ID: {$medal->id})");
            $medalRep = $this->medalRepository;
            $medalRep->userAttachMedal($user, $medal);
            if ($medal->inventory !== null) {
                $affectedRows = DB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory');
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) inventory affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $medalId
     * @param  mixed  $toUid
     */
    public function consumeToGiftMedal($uid, $medalId, $toUid): bool
    {
        $user = User::query()->findOrFail((int) $uid);
        $toUser = User::query()->findOrFail((int) $toUid);
        $medal = Medal::query()->findOrFail((int) $medalId);
        $exists = $toUser->valid_medals()->where('medal_id', $medalId)->exists();
        Logger::writeWithContext((string) LegacyDb::lastQuery(false, 'json'), (string) 'info', (bool) false);
        if ($exists) {
            throw new \LogicException("user: $toUid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $giftFee = $medal->price * ($medal->gift_fee_factor ?? 0);
        $requireBonus = $medal->price + $giftFee;
        DB::transaction(function () use ($user, $toUser, $medal, $requireBonus, $giftFee) {
            $comment = Locale::trans('bonus.comment_gift_medal', ['bonus' => $requireBonus, 'medal_name' => $medal->name, 'to_username' => $toUser->username], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::GIFT_MEDAL->value, "$comment(medal ID: {$medal->id})");

            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays((int) $medal->duration)->toDateTimeString();
            }
            $msg = [
                'sender' => null,
                'receiver' => $toUser->id,
                'subject' => Locale::trans('message.receive_medal.subject', [], $toUser->locale),
                'msg' => Locale::trans('message.receive_medal.body', ['username' => $user->username, 'cost_bonus' => $requireBonus, 'medal_name' => $medal->name, 'price' => $medal->price, 'gift_fee_total' => $giftFee, 'gift_fee_factor' => $medal->gift_fee_factor ?? 0, 'expire_at' => $expireAt ?? Locale::trans('label.permanent', [], null), 'bonus_addition_factor' => $medal->bonus_addition_factor ?? 0], $toUser->locale),
                'added' => now(),
            ];
            Message::add($msg);
            $toUser->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedalStatus::NOT_WEARING->value]]);
            if ($medal->inventory !== null) {
                $affectedRows = DB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory');
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) inventory affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    /** @param  mixed  $uid */
    public function consumeToBuyAttendanceCard($uid): bool
    {
        $user = User::query()->findOrFail((int) $uid);
        $requireBonus = BonusLogs::getBonusForBuyAttendanceCard();
        DB::transaction(function () use ($user, $requireBonus) {
            $comment = Locale::trans('bonus.comment_buy_attendance_card', ['bonus' => $requireBonus], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_ATTENDANCE_CARD->value, $comment);
            User::query()->where('id', $user->id)->increment('attendance_card');
        });

        return true;

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $count
     */
    public function consumeToBuyTemporaryInvite($uid, $count = 1): bool
    {
        $requireBonus = BonusLogs::getBonusForBuyTemporaryInvite();
        if ($requireBonus <= 0) {
            throw new \RuntimeException('Temporary invite require bonus <= 0 !');
        }
        $user = User::query()->findOrFail((int) $uid);
        $toolRep = $this->toolRepository;
        $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
        DB::transaction(function () use ($user, $requireBonus, $hashArr) {
            $comment = Locale::trans('bonus.comment_buy_temporary_invite', ['bonus' => $requireBonus, 'count' => count($hashArr)], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_TEMPORARY_INVITE->value, $comment);
            $invites = [];
            foreach ($hashArr as $hash) {
                $invites[] = [
                    'inviter' => $user->id,
                    'invitee' => '',
                    'hash' => $hash,
                    'valid' => 0,
                    'expired_at' => Carbon::now()->addDays(Invite::TEMPORARY_INVITE_VALID_DAYS),
                    'created_at' => Carbon::now(),
                ];
            }
            Invite::query()->insert($invites);
        });

        return true;

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $duration
     */
    public function consumeToBuyRainbowId($uid, $duration = 30): bool
    {
        $user = User::query()->findOrFail((int) $uid);
        $requireBonus = BonusLogs::getBonusForBuyRainbowId();
        DB::transaction(function () use ($user, $requireBonus, $duration) {
            $comment = Locale::trans('bonus.comment_buy_rainbow_id', ['bonus' => $requireBonus, 'duration' => $duration], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_RAINBOW_ID->value, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
                'duration' => $duration,
            ];
            $userRep = $this->userRepository;
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    /** @param  mixed  $uid */
    public function consumeToBuyChangeUsernameCard($uid): bool
    {
        $user = User::query()->findOrFail((int) $uid);
        $requireBonus = BonusLogs::getBonusForBuyChangeUsernameCard();
        if (UserMeta::query()->where('uid', $uid)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
            throw new NexusException('user already has change username card');
        }
        DB::transaction(function () use ($user, $requireBonus) {
            $comment = Locale::trans('bonus.comment_buy_change_username_card', ['bonus' => $requireBonus], $user->locale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_CHANGE_USERNAME_CARD->value, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_CHANGE_USERNAME,
            ];
            $userRep = $this->userRepository;
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $channel
     */
    public function consumeToBuyTorrent($uid, $torrentId, $channel = 'Web'): TorrentBuyLog
    {
        $torrent = Torrent::query()->findOrFail((int) $torrentId, Torrent::$commentFields);
        $requireBonus = $torrent->price;

        return DB::transaction(function () use ($requireBonus, $torrent, $channel, $uid) {
            // consumeUserBonus now locks the user row internally via lockForUpdate,
            // so we don't need a separate lockForUpdate here.
            $user = User::query()->findOrFail((int) $uid);
            $buyerLocale = $user->locale;
            $comment = Locale::trans('bonus.comment_buy_torrent', ['bonus' => $requireBonus, 'torrent_id' => $torrent->id], $buyerLocale);
            Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BusinessType::BUY_TORRENT->value, $comment);
            $buyLog = TorrentBuyLog::query()->create([
                'uid' => $user->id,
                'torrent_id' => $torrent->id,
                'price' => $requireBonus,
                'channel' => $channel,
            ]);
            // increment owner bonus
            $taxFactor = SiteConfig::current()->torrent->taxFactor();
            if ($taxFactor < 0 || $taxFactor > 1) {
                throw new \RuntimeException("Invalid tax_factor: $taxFactor");
            }
            $increaseBonus = $requireBonus * (1 - $taxFactor);
            $owner = $torrent->user;
            if ($owner->id) {
                $nowStr = now()->toDateTimeString();
                $businessType = BusinessType::TORRENT_BE_DOWNLOADED->value;
                $owner->increment('seedbonus', $increaseBonus);
                $comment = Locale::trans('bonus.comment_torrent_be_downloaded', ['username' => $user->username, 'uid' => $user->id], $owner->locale);
                $bonusLog = [
                    'business_type' => $businessType,
                    'uid' => $owner->id,
                    'old_total_value' => (float) $owner->seedbonus,
                    'value' => $increaseBonus,
                    'new_total_value' => bcadd((string) ($owner->seedbonus ?? 0), (string) $increaseBonus),
                    'comment' => sprintf('[%s] %s', BusinessType::TORRENT_BE_DOWNLOADED->label(), $comment),
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                BonusLogs::query()->insert($bonusLog);
            }
            $buyTorrentSuccessMessage = [
                'sender' => null,
                'receiver' => $user->id,
                'added' => now(),
                'subject' => Locale::trans('message.buy_torrent_success.subject', [], $buyerLocale),
                'msg' => Locale::trans('message.buy_torrent_success.body', ['torrent_name' => $torrent->name, 'bonus' => $requireBonus, 'url' => sprintf('details.php?id=%s&hit=1', $torrent->id)], $buyerLocale),
            ];
            Message::add($buyTorrentSuccessMessage);

            // T-24: Record purchase completed event in outbox (same transaction)
            $this->outboxService->recordPurchaseCompleted(
                userId: (int) $user->id,
                torrentId: (int) $torrent->id,
                purchaseData: [
                    'price' => $requireBonus,
                    'channel' => $channel,
                    'owner_id' => $owner->id ?? null,
                ],
            );

            return $buyLog;
        });
    }

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
