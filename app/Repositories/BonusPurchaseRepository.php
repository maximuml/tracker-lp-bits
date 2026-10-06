<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Enums\HitAndRunStatus;
use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Models\UserMeta;
use App\Services\OutboxService;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bonus purchase operations: invite/card/rainbow-id/torrent
 * purchases, H&R cancellation, and the core consumeUserBonus mutation.
 *
 * Extracted from BonusRepository to keep both classes under the
 * 400-line ratchet.
 */
class BonusPurchaseRepository extends BaseRepository
{
    public function __construct(
        private readonly OutboxService $outboxService,
        private readonly ToolRepository $toolRepository,
        private readonly UserRepository $userRepository,
        private readonly BonusConsumptionRepository $consumptionRepository,
        private readonly MessageRepository $messageRepository,
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

            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::CANCEL_HIT_AND_RUN->value, "$comment(H&R ID: {$hitAndRun->id})");

            $existingComment = (string) $hitAndRun->comment;
            $newComment = $existingComment === '' ? $comment : $comment."\n".$existingComment;
            $hitAndRun->update([
                'status' => HitAndRunStatus::PARDONED->value,
                'comment' => $newComment,
            ]);
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
            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::BUY_ATTENDANCE_CARD->value, $comment);
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
            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::BUY_TEMPORARY_INVITE->value, $comment);
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
            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::BUY_RAINBOW_ID->value, $comment);
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
            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::BUY_CHANGE_USERNAME_CARD->value, $comment);
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
            $this->consumptionRepository->consumeUserBonus($user, $requireBonus, BusinessType::BUY_TORRENT->value, $comment);
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
                'msg' => Locale::trans('message.buy_torrent_success.body', ['torrent_name' => $torrent->name, 'bonus' => $requireBonus, 'url' => sprintf('/web/details/%s?hit=1', $torrent->id)], $buyerLocale),
            ];
            $this->messageRepository->add($buyTorrentSuccessMessage);

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
}
