<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BusinessType;
use App\Enums\HitAndRunStatus;
use App\Enums\UserEnabled;
use App\Enums\UserMedalStatus;
use App\Enums\UserStatus;
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
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BonusRepository extends BaseRepository
{
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

    public function getCharityReceiverCount(float $ratioCharity): int
    {
        return (int) User::query()
            ->where('enabled', 'yes')
            ->where('downloaded', '>', 10737418240)
            ->whereRaw('? > uploaded/downloaded', [$ratioCharity])
            ->count();
    }

    /**
     * @return int number of affected rows
     */
    public function incrementSeedbonusForLowRatioReceivers(float $ratioCharity, float $amount): int
    {
        return User::query()
            ->where('enabled', 'yes')
            ->where('downloaded', '>', 10737418240)
            ->whereRaw('? > uploaded/downloaded', [$ratioCharity])
            ->increment('seedbonus', $amount);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findGiftReceiver(string $username): ?array
    {
        $receiver = User::query()->where('username', $username)->first(['id', 'seedbonus']);

        return $receiver ? $receiver->toArray() : null;
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
            $medalRep = app(MedalRepository::class);
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
        $toolRep = app(ToolRepository::class);
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
            $userRep = app(UserRepository::class);
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    public function hasChangeUsernameCard(int $userId): bool
    {
        return UserMeta::query()->where('uid', $userId)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists();
    }

    public function hasRainbowIdForever(int $userId): bool
    {
        return UserMeta::query()->where('uid', $userId)->where('meta_key', UserMeta::META_KEY_PERSONALIZED_USERNAME)->whereNull('deadline')->exists();
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
            $userRep = app(UserRepository::class);
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
            $userQuery = User::query();
            if ($requireBonus > 0) {
                $userQuery = $userQuery->lockForUpdate();
            }
            $user = $userQuery->findOrFail((int) $uid);
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
        if ($user->seedbonus < $requireBonus) {
            Logger::writeWithContext((string) "user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: {$requireBonus}", (string) 'error', (bool) false);
            throw new \LogicException('User bonus not enough.');
        }
        DB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logBusinessTypeEnum, $logComment, $userUpdates) {
            $oldUserBonus = (float) ($user->seedbonus ?? 0);
            $newUserBonus = bcsub((string) $oldUserBonus, (string) $requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
            $userUpdates['seedbonus'] = $newUserBonus;
            $affectedRows = DB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ('update user seedbonus affected rows: '.$affectedRows.' != 1, query: '.LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                throw new \RuntimeException('Update user seedbonus fail.');
            }
            $nowStr = now()->toDateTimeString();
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', $logBusinessTypeEnum->label(), $logComment),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            BonusLogs::query()->insert($bonusLog);
            Logger::writeWithContext((string) ('bonusLog: '.Json::encode($bonusLog)), (string) 'info', (bool) false);
            Cache::clearUser($user->id, (string) $user->passkey);
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
            'charity' => DB::raw('charity + '.(float) $charityIncrement), // @phpstan-ignore argument.type
        ]);
    }

    public function getCount(string $category = '', int $userId = 0, int $businessType = 0): int
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);

            return $query->count();
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @return mixed
     */
    public function getList(string $category = '', int $userId = 0, int $businessType = 0, int $page = 1, int $perPage = 50)
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);

            return $query->orderBy('id', 'desc')->forPage($page, $perPage)->get();
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @return Builder<BonusLogs>
     */
    private function buildQuery(int $userId = 0, int $businessType = 0): Builder
    {
        $query = BonusLogs::query();
        if ($userId > 0) {
            $query->where('uid', $userId);
        }
        if ($businessType > 0) {
            $query->where('business_type', $businessType);
        }

        return $query;
    }

    /**
     * @param  array<int>|null  $torrentIdArr
     * @return array{torrentResult: array<int, array<string, mixed>>, sql: string}
     */
    public function getTorrentRowsForBonusCalculation(int $uid, ?array $torrentIdArr, int|float $minSize): array
    {
        if ($torrentIdArr !== null) {
            if (empty($torrentIdArr)) {
                $torrentIdArr = [-1];
            }
            $torrentQuery = DB::table('torrents')
                ->whereIn('id', $torrentIdArr)
                ->where('size', '>=', $minSize)
                ->select('id', 'added', 'size', 'seeders', DB::raw("'NO_PEER_ID' as peerID"), DB::raw("'' as last_action"), DB::raw("'' as ip"));
        } else {
            $torrentQuery = DB::table('torrents')
                ->leftJoin('peers', 'peers.torrent', '=', 'torrents.id')
                ->where('peers.userid', $uid)
                ->where('peers.seeder', 'yes')
                ->where('torrents.size', '>', $minSize)
                ->groupBy('torrents.id', 'peers.id')
                ->select('torrents.id', 'torrents.added', 'torrents.size', 'torrents.seeders', 'peers.id as peerID', 'peers.last_action', 'peers.ip');
        }

        return [
            'sql' => $torrentQuery->toSql(),
            'torrentResult' => $torrentQuery->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /**
     * @param  array<int, int>  $torrentIds
     * @return array<int, array<int, int>>
     */
    public function getTagGrouped(array $torrentIds): array
    {
        if (empty($torrentIds)) {
            return [];
        }

        $tagGrouped = [];
        $tagResult = DB::table('torrent_tags')
            ->whereIn('torrent_id', $torrentIds)
            ->select('torrent_id', 'tag_id')
            ->get();
        foreach ($tagResult as $tagItem) {
            $tagGrouped[$tagItem->torrent_id][$tagItem->tag_id] = 1;
        }

        return $tagGrouped;
    }

    public function getMedalAdditionalFactor(int $uid, string $nowStr): float
    {
        $medalQuery = DB::table('medals')
            ->whereIn('id', function ($query) use ($uid, $nowStr) {
                $query->select('medal_id')
                    ->from('user_medals')
                    ->where('uid', $uid)
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('expire_at')->orWhere('expire_at', '>', $nowStr);
                    })
                    ->where(function ($q) use ($nowStr) {
                        $q->whereNull('bonus_addition_expire_at')->orWhere('bonus_addition_expire_at', '>', $nowStr);
                    });
            });

        if (DB::connection()->getDriverName() === 'mysql') {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor), 5) as factor');
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            $medalQuery->selectRaw('round(sum(bonus_addition_factor)::numeric, 5) as factor');
        } else {
            throw new \RuntimeException('Not supported database');
        }

        return floatval($medalQuery->value('factor') ?? 0);
    }

    public function getHaremAddition(int|string $uid): float|int|string
    {
        $addition = DB::table('users')
            ->where('invited_by', $uid)
            ->where('status', UserStatus::CONFIRMED->value)
            ->where('enabled', UserEnabled::YES->value)
            ->sum('seed_points_per_hour');

        Logger::writeWithContext("[HAREM_ADDITION], user: $uid, addition: $addition");

        return $addition;
    }

    public function updateSeedBonus(string $op, float $point, int|string $id): void
    {
        if (! in_array($op, ['+', '-'], true)) {
            throw new \InvalidArgumentException('Invalid seedbonus operation: '.$op);
        }
        DB::table('users')
            ->where('id', $id)
            ->update([
                'seedbonus' => DB::raw('seedbonus '.$op.' '.(float) $point), // @phpstan-ignore argument.type
            ]);
    }
}
