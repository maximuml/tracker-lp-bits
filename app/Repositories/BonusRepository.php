<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Invite;
use App\Models\Medal;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Models\UserMedal;
use App\Models\UserMeta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nexus\Database\ClickHouse;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    /**
     * @param  mixed  $uid
     * @param  mixed  $hitAndRunId
     */
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (!HitAndRun::getIsEnabled()) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        if ($hitAndRun->status == HitAndRun::STATUS_PARDONED) {
            throw new \LogicException("H&R: $hitAndRunId already pardoned.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = \App\Support\Locale::trans('hr.bonus_cancel_comment', ['bonus' => $requireBonus], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);

            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN, "$comment(H&R ID: {$hitAndRun->id})");

            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => NexusDB::raw("if(comment = '', '$comment', concat_ws('\n', '$comment', comment))"),
            ]);
        });

        return true;

    }


    /**
     * @param  mixed  $uid
     * @param  mixed  $medalId
     */
    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail($uid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        \App\Support\Logger::writeWithContext((string) \App\Support\LegacyDb::lastQuery(false, 'json'), (string) 'info', (bool) false);
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $requireBonus = $medal->price;
        NexusDB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = \App\Support\Locale::trans('bonus.comment_buy_medal', ['bonus' => $requireBonus, 'medal_name' => $medal->name], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_MEDAL, "$comment(medal ID: {$medal->id})");
            $medalRep = new MedalRepository();
            $medalRep->userAttachMedal($user, $medal);
            if ($medal->inventory !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory')
                ;
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
        $user = User::query()->findOrFail($uid);
        $toUser = User::query()->findOrFail($toUid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $toUser->valid_medals()->where('medal_id', $medalId)->exists();
        \App\Support\Logger::writeWithContext((string) \App\Support\LegacyDb::lastQuery(false, 'json'), (string) 'info', (bool) false);
        if ($exists) {
            throw new \LogicException("user: $toUid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $giftFee = $medal->price * ($medal->gift_fee_factor ?? 0);
        $requireBonus = $medal->price + $giftFee;
        NexusDB::transaction(function () use ($user, $toUser, $medal, $requireBonus, $giftFee) {
            $comment = \App\Support\Locale::trans('bonus.comment_gift_medal', ['bonus' => $requireBonus, 'medal_name' => $medal->name, 'to_username' => $toUser->username], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_GIFT_MEDAL, "$comment(medal ID: {$medal->id})");

            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays((int)$medal->duration)->toDateTimeString();
            }
            $msg = [
                'sender' => 0,
                'receiver' => $toUser->id,
                'subject' => \App\Support\Locale::trans('message.receive_medal.subject', [], $toUser->locale),
                'msg' => \App\Support\Locale::trans('message.receive_medal.body', ['username' => $user->username, 'cost_bonus' => $requireBonus, 'medal_name' => $medal->name, 'price' => $medal->price, 'gift_fee_total' => $giftFee, 'gift_fee_factor' => $medal->gift_fee_factor ?? 0, 'expire_at' => $expireAt ?? \App\Support\Locale::trans('label.permanent', [], null), 'bonus_addition_factor' => $medal->bonus_addition_factor ?? 0], $toUser->locale),
                'added' => now()
            ];
            Message::add($msg);
            $toUser->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);
            if ($medal->inventory !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('inventory', $medal->inventory)
                    ->decrement('inventory')
                ;
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
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyAttendanceCard();
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = \App\Support\Locale::trans('bonus.comment_buy_attendance_card', ['bonus' => $requireBonus], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD, $comment);
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
            throw new \RuntimeException("Temporary invite require bonus <= 0 !");
        }
        $user = User::query()->findOrFail($uid);
        $toolRep = new ToolRepository();
        $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
        NexusDB::transaction(function () use ($user, $requireBonus, $hashArr) {
            $comment = \App\Support\Locale::trans('bonus.comment_buy_temporary_invite', ['bonus' => $requireBonus, 'count' => count($hashArr)], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE, $comment);
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
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyRainbowId();
        NexusDB::transaction(function () use ($user, $requireBonus, $duration) {
            $comment = \App\Support\Locale::trans('bonus.comment_buy_rainbow_id', ['bonus' => $requireBonus, 'duration' => $duration], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
                'duration' => $duration,
            ];
            $userRep = new UserRepository();
            $userRep->addMeta($user, $metaData, $metaData, false);
        });

        return true;

    }

    /** @param  mixed  $uid */
    public function consumeToBuyChangeUsernameCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyChangeUsernameCard();
        if (UserMeta::query()->where('uid', $uid)->where('meta_key', UserMeta::META_KEY_CHANGE_USERNAME)->exists()) {
            throw new NexusException("user already has change username card");
        }
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = \App\Support\Locale::trans('bonus.comment_buy_change_username_card', ['bonus' => $requireBonus], $user->locale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD, $comment);
            $metaData = [
                'meta_key' => UserMeta::META_KEY_CHANGE_USERNAME,
            ];
            $userRep = new UserRepository();
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
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $requireBonus = $torrent->price;
        return NexusDB::transaction(function () use ($requireBonus, $torrent, $channel, $uid) {
            $userQuery = User::query();
            if ($requireBonus > 0) {
                $userQuery = $userQuery->lockForUpdate();
            }
            $user = $userQuery->findOrFail($uid);
            $buyerLocale = $user->locale;
            $comment = \App\Support\Locale::trans('bonus.comment_buy_torrent', ['bonus' => $requireBonus, 'torrent_id' => $torrent->id], $buyerLocale);
            \App\Support\Logger::writeWithContext((string) "comment: {$comment}", (string) 'info', (bool) false);
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_TORRENT, $comment);
            $buyLog = TorrentBuyLog::query()->create([
                'uid' => $user->id,
                'torrent_id' => $torrent->id,
                'price' => $requireBonus,
                'channel' => $channel,
            ]);
            //increment owner bonus
            $taxFactor = \App\Support\Config\SiteConfig::current()->torrent->taxFactor();
            if ($taxFactor < 0 || $taxFactor > 1) {
                throw new \RuntimeException("Invalid tax_factor: $taxFactor");
            }
            $increaseBonus = $requireBonus * (1 - $taxFactor);
            $owner = $torrent->user;
            if ($owner->id) {
                $nowStr = now()->toDateTimeString();
                $businessType = BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED;
                $owner->increment('seedbonus', $increaseBonus);
                $comment = \App\Support\Locale::trans('bonus.comment_torrent_be_downloaded', ['username' => $user->username, 'uid' => $user->id], $owner->locale);
                $bonusLog = [
                    'business_type' => $businessType,
                    'uid' => $owner->id,
                    'old_total_value' => $owner->seedbonus,
                    'value' => $increaseBonus,
                    'new_total_value' => bcadd((string)$owner->seedbonus, (string)$increaseBonus),
                    'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$businessType]['text'], $comment),
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];
                BonusLogs::query()->insert($bonusLog);
            }
            $buyTorrentSuccessMessage = [
                'sender' => 0,
                'receiver' => $user->id,
                'added' => now(),
                'subject' => \App\Support\Locale::trans("message.buy_torrent_success.subject", [], $buyerLocale),
                'msg' => \App\Support\Locale::trans("message.buy_torrent_success.body", ['torrent_name' => $torrent->name, 'bonus' => $requireBonus, 'url' => sprintf('details.php?id=%s&hit=1', $torrent->id)], $buyerLocale),
            ];
            Message::add($buyTorrentSuccessMessage);
            return $buyLog;
        });
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $requireBonus
     * @param  mixed  $logBusinessType
     * @param  mixed  $logComment
     * @param  array<int|string, mixed>  $userUpdates
     * @return  void
     */
    public function consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment = '', array $userUpdates = [])
    {
        if (!isset(BonusLogs::$businessTypes[$logBusinessType])) {
            throw new \InvalidArgumentException("Invalid logBusinessType: $logBusinessType");
        }
        if (isset($userUpdates['seedbonus']) || isset($userUpdates['bonuscomment']) || isset($userUpdates['modcomment'])) {
            throw new \InvalidArgumentException("Not support update seedbonus or bonuscomment or modcomment");
        }
        if ($requireBonus <= 0) {
            return;
        }
        $user = $this->getUser($user);
        if ($user->seedbonus < $requireBonus) {
            \App\Support\Logger::writeWithContext((string) "user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: {$requireBonus}", (string) 'error', (bool) false);
            throw new \LogicException("User bonus not enough.");
        }
        NexusDB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logComment, $userUpdates) {
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub((string)$oldUserBonus, (string)$requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            \App\Support\Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
            $userUpdates['seedbonus'] = $newUserBonus;
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                \App\Support\Logger::writeWithContext((string) ("update user seedbonus affected rows: " . $affectedRows . " != 1, query: " . \App\Support\LegacyDb::lastQuery(false, 'json')), (string) 'error', (bool) false);
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $nowStr = now()->toDateTimeString();
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$logBusinessType]['text'], $logComment),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            BonusLogs::query()->insert($bonusLog);
            \App\Support\Logger::writeWithContext((string) ("bonusLog: " . \App\Support\Json::encode($bonusLog)), (string) 'info', (bool) false);
            \App\Support\Cache::clearUser($user->id, $user->passkey);
        });
    }

    /**
     * @param  string  $category
     * @param  int  $userId
     * @param  int  $businessType
     */
    public function getCount(string $category = '', int $userId = 0, int $businessType = 0): int
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);
            return $query->count();
        } else if ($category == BonusLogs::CATEGORY_SEEDING) {
            list($whereStr, $binds) = $this->buildWhereStrAndBinds($userId, $businessType);
            return ClickHouse::count("bonus_logs", $whereStr, $binds);
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @param  string  $category
     * @param  int  $userId
     * @param  int  $businessType
     * @param  int  $page
     * @param  int  $perPage
     * @return  mixed
     */
    public function getList(string $category = '', int $userId = 0, int $businessType = 0, int $page = 1, int $perPage = 50)
    {
        if ($category == BonusLogs::CATEGORY_COMMON) {
            $query = $this->buildQuery($userId, $businessType);
            return $query->orderBy("id", "desc")->forPage($page, $perPage)->get();
        } else if ($category == BonusLogs::CATEGORY_SEEDING) {
            list($whereStr, $binds) = $this->buildWhereStrAndBinds($userId, $businessType);
            $offset = ($page - 1) * $perPage;
            $rows = ClickHouse::list("select * from bonus_logs $whereStr order by created_at desc limit $offset, $perPage", $binds);
            $result = [];
            $id = 1;//fake id
            foreach ($rows as $row) {
                $record = new BonusLogs($row);
                $record->id = $id;
                $result[] = $record;
                $id++;
            }
            return $result;
        }
        throw new \InvalidArgumentException("Invalid category: $category");
    }

    /**
     * @param  int  $userId
     * @param  int  $businessType
     * @return  array<int|string, mixed>
     */
    private function buildWhereStrAndBinds(int $userId = 0,  int $businessType = 0)
    {
        $whereArr = [];
        $binds = [];
        if ($userId > 0) {
            $whereArr[] = "uid = :uid";
            $binds['uid'] = $userId;
        }
        if ($businessType > 0) {
            $whereArr[] = "business_type = :business_type";
            $binds["business_type"] = $businessType;
        }
        if (empty($whereArr)) {
            $whereStr = "";
        } else {
            $whereStr = sprintf("where %s", implode(' AND ', $whereArr));
        }
        return [$whereStr, $binds];
    }

    /**
     * @param  int  $userId
     * @param  int  $businessType
     * @return  \Illuminate\Database\Eloquent\Builder<BonusLogs>
     */
    private function buildQuery(int $userId = 0,  int $businessType = 0): Builder
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


}
