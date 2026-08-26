<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Enums\ModelEventEnum;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Repositories\UserRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Database;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Time;
use App\Support\TorrentOps;
use App\Support\UserOps;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent cleanup task implementations drained from the legacy `docleanup()`.
 *
 * Each public method performs one self-contained cleanup operation and returns
 * a short log message. Callers are responsible for locking and scheduling.
 */
final class Tasks
{
    /**
     * Priority Class 1: remove peers whose last_action is older than the dead
     * threshold.
     */
    public function prunePeers(): string
    {
        $deadtime = date('Y-m-d H:i:s', Time::deadThreshold(
            (int) SiteConfig::current()->main->anninterthree(3600),
            time()
        ));

        DB::table('peers')->where('last_action', '<', $deadtime)->delete();

        return 'update peer status';
    }

    /**
     * Priority Class 1: reset per-hour seed bonus counters for users whose seeding
     * snapshot is older than two autoclean intervals.
     */
    public function resetSeedBonusCounters(): string
    {
        $interval = (int) SiteConfig::current()->main->autocleanIntervalOne(900);
        $cutoff = Carbon::now()->subSeconds(2 * $interval)->toDateTimeString();

        DB::table('users')
            ->where('seed_points_updated_at', '<', $cutoff)
            ->update([
                'seed_points_per_hour' => 0,
                'seed_bonus_per_hour' => 0,
                'seeding_torrent_count' => 0,
                'seeding_torrent_size' => 0,
            ]);

        return 'reset seed bonus counters';
    }

    /**
     * Priority Class 2: mark torrents with no seeders and stale last_action as
     * invisible.
     */
    public function updateTorrentVisibility(): string
    {
        $maxDeadTime = (int) SiteConfig::current()->main->maxDeadTorrentTime(21600);
        $deadtime = Time::deadThreshold((int) SiteConfig::current()->main->anninterthree(3600), time()) - $maxDeadTime;
        $lastActionDeadTime = date('Y-m-d H:i:s', $deadtime);

        DB::table('torrents')
            ->where('visible', 'yes')
            ->where('last_action', '<', $lastActionDeadTime)
            ->where('seeders', 0)
            ->update(['visible' => 'no']);

        return "update torrents' visibility";
    }

    /**
     * Priority Class 3: recompute post/topic counts for every forum.
     */
    public function updateForumCounts(): string
    {
        $forumIds = DB::table('forums')->pluck('id');

        foreach ($forumIds as $forumId) {
            $postcount = 0;
            $topiccount = 0;
            $topicIds = DB::table('topics')->where('forumid', $forumId)->pluck('id');

            foreach ($topicIds as $topicId) {
                $postcount += (int) DB::table('posts')->where('topicid', $topicId)->count();
                $topiccount++;
            }

            DB::table('forums')
                ->where('id', $forumId)
                ->update(['postcount' => $postcount, 'topiccount' => $topiccount]);
        }

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('forums_list');
        }

        return 'update forum post/topic count';
    }

    /**
     * Priority Class 3: delete offers that were never voted on and offers that
     * were approved but never uploaded.
     */
    public function pruneOffers(): string
    {
        $offerVoteTimeout = (int) SiteConfig::current()->main->offerVoteTimeout(259200);
        if ($offerVoteTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerVoteTimeout);
            $offerIds = DB::table('offers')
                ->where('added', '<', $dt)
                ->where('allowed', '<>', 'allowed')
                ->pluck('id', 'name')
                ->all();

            $this->deleteOffers($offerIds, 'vote timeout');
        }

        $offerUploadTimeout = (int) SiteConfig::current()->main->offerUploadTimeout(86400);
        if ($offerUploadTimeout > 0) {
            $dt = date('Y-m-d H:i:s', time() - $offerUploadTimeout);
            $offerIds = DB::table('offers')
                ->where('allowedtime', '<', $dt)
                ->where('allowed', 'allowed')
                ->pluck('id', 'name')
                ->all();

            $this->deleteOffers($offerIds, 'upload timeout');
        }

        return 'delete offers if not voted on / uploaded after some time';
    }

    /**
     * Priority Class 3: expire time-based global torrent promotions.
     */
    public function expireTorrentPromotions(): string
    {
        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireHalfleech(0),
            Torrent::PROMOTION_HALF_DOWN,
            (int) SiteConfig::current()->torrent->halfleechbecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireFree(0),
            Torrent::PROMOTION_FREE,
            (int) SiteConfig::current()->torrent->freebecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwoup(0),
            Torrent::PROMOTION_TWO_TIMES_UP,
            (int) SiteConfig::current()->torrent->twoupbecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwoupfree(0),
            Torrent::PROMOTION_FREE_TWO_TIMES_UP,
            (int) SiteConfig::current()->torrent->twoupfreebecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireTwouphalfleech(0),
            Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP,
            (int) SiteConfig::current()->torrent->twouphalfleechbecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireThirtypercentleech(0),
            Torrent::PROMOTION_ONE_THIRD_DOWN,
            (int) SiteConfig::current()->torrent->thirtypercentleechbecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expirePromotionType(
            (int) SiteConfig::current()->torrent->expireNormal(0),
            Torrent::PROMOTION_NORMAL,
            (int) SiteConfig::current()->torrent->normalbecome(Torrent::PROMOTION_NORMAL),
        );

        $this->expireIndividualPromotions();

        return 'expire torrent promotion';
    }

    /**
     * Priority Class 3: expire sticky position states.
     */
    public function expireTorrentSticky(): string
    {
        $toBeExpirePosStates = [Torrent::POS_STATE_STICKY_FIRST, Torrent::POS_STATE_STICKY_SECOND];

        Torrent::query()
            ->whereIn('pos_state', $toBeExpirePosStates)
            ->whereNotNull('pos_state_until')
            ->where('pos_state_until', '<', now())
            ->update([
                'pos_state' => Torrent::POS_STATE_STICKY_NONE,
                'pos_state_until' => null,
            ]);

        return 'expire torrent pos state';
    }

    /**
     * Priority Class 4: delete unconfirmed accounts, old login attempts, invite
     * codes and regimage records.
     */
    public function cleanupStaleAuth(): string
    {
        $this->deleteUnconfirmedAccounts();
        $this->deleteOldLoginAttempts();
        $this->deleteOldInviteCodes();
        $this->deleteRegimages();

        return 'cleanup stale auth records';
    }

    /**
     * Priority Class 4: disable or destroy inactive user accounts.
     */
    public function disableInactiveUsers(): string
    {
        $this->disableNoTransferByLastAccess();
        $this->disableNoTransferByRegisterTime();
        $this->disableNotParked();
        $this->disableParked();
        $this->destroyDisabledAccounts();

        return 'disable/destroy inactive user accounts';
    }

    /**
     * Priority Class 4: promote/demote users and ban leech-warning expiries.
     */
    public function manageUserClasses(): string
    {
        $this->promotePeasantsToUsers();
        $this->promoteUsersByClass();
        $this->demoteUsersByClass();
        $this->demoteUsersToPeasant();
        $this->banLeechWarningExpired();

        return 'manage user classes';
    }

    /**
     * Priority Class 4: delete dead torrents, old IP logs, and stale failed jobs.
     */
    public function cleanupDeadTorrentsAndIpLogs(): string
    {
        $this->deleteDeadTorrents();
        $this->deleteOldIpLogs();
        $this->deleteFailedJobs();

        return 'delete dead torrents, old IP logs and failed jobs';
    }

    // ------------------------------------------------------------------------
    // Offer helpers
    // ------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $offerIds
     */
    private function deleteOffers(array $offerIds, string $reason): void
    {
        if ($offerIds === []) {
            return;
        }

        $ids = array_values($offerIds);

        DB::table('offervotes')->whereIn('offerid', $ids)->delete();
        DB::table('comments')->whereIn('offer', $ids)->delete();
        DB::table('offers')->whereIn('id', $ids)->delete();

        foreach ($offerIds as $name => $id) {
            Log::write("Offer {$id} ({$name}) was deleted by system ({$reason})", 'normal');
        }
    }

    // ------------------------------------------------------------------------
    // Torrent promotion helpers
    // ------------------------------------------------------------------------

    private function expirePromotionType(int $days, int $fromState, int $toState): void
    {
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        $validStates = [
            Torrent::PROMOTION_NORMAL,
            Torrent::PROMOTION_FREE,
            Torrent::PROMOTION_TWO_TIMES_UP,
            Torrent::PROMOTION_FREE_TWO_TIMES_UP,
            Torrent::PROMOTION_HALF_DOWN,
            Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP,
        ];
        $targetState = in_array($toState, $validStates, true) ? $toState : Torrent::PROMOTION_NORMAL;

        $becomeMap = [
            Torrent::PROMOTION_NORMAL => 'normal',
            Torrent::PROMOTION_FREE => 'Free',
            Torrent::PROMOTION_TWO_TIMES_UP => '2X',
            Torrent::PROMOTION_FREE_TWO_TIMES_UP => '2X Free',
            Torrent::PROMOTION_HALF_DOWN => '50%',
            Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP => '2X 50%',
        ];
        $become = $becomeMap[$targetState];

        $torrents = DB::table('torrents')
            ->where('added', '<', $dt)
            ->where('sp_state', $fromState)
            ->where('promotion_time_type', Torrent::PROMOTION_TIME_TYPE_GLOBAL)
            ->get(['id', 'name']);

        foreach ($torrents as $torrent) {
            $arr = (array) $torrent;

            DB::table('torrents')
                ->where('id', $arr['id'])
                ->update(['sp_state' => $targetState]);

            Events::publishModel(ModelEventEnum::TORRENT_UPDATED, (int) $arr['id']);

            if ($targetState === Torrent::PROMOTION_NORMAL) {
                Log::write("Torrent {$arr['id']} ({$arr['name']}) is no longer on promotion (time expired)", 'normal');
            } else {
                Log::write("Promotion type for torrent {$arr['id']} ({$arr['name']}) is changed to {$become} (time expired)", 'normal');
            }
        }
    }

    private function expireIndividualPromotions(): void
    {
        $torrents = Torrent::query()
            ->where('promotion_time_type', Torrent::PROMOTION_TIME_TYPE_DEADLINE)
            ->where('promotion_until', '<', now())
            ->get(['id']);

        foreach ($torrents as $torrent) {
            Torrent::query()->where('id', $torrent->id)->update([
                'sp_state' => Torrent::PROMOTION_NORMAL,
                'promotion_time_type' => Torrent::PROMOTION_TIME_TYPE_GLOBAL,
                'promotion_until' => null,
            ]);

            Events::publishModel(ModelEventEnum::TORRENT_UPDATED, $torrent->id);
        }
    }

    // ------------------------------------------------------------------------
    // Stale auth helpers
    // ------------------------------------------------------------------------

    private function deleteUnconfirmedAccounts(): void
    {
        $signupTimeout = (int) SiteConfig::current()->main->signupTimeout(259200);
        $deadtime = time() - $signupTimeout;

        User::query()
            ->where('status', User::STATUS_PENDING)
            ->whereRaw('added < FROM_UNIXTIME(?)', [$deadtime])
            ->whereRaw('last_login < FROM_UNIXTIME(?)', [$deadtime])
            ->whereRaw('last_access < FROM_UNIXTIME(?)', [$deadtime])
            ->delete();
    }

    private function deleteOldLoginAttempts(): void
    {
        $secs = 12 * 60 * 60;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        DB::table('loginattempts')
            ->where('banned', 'no')
            ->where('added', '<', $dt)
            ->delete();
    }

    private function deleteOldInviteCodes(): void
    {
        $inviteTimeout = (int) SiteConfig::current()->main->inviteTimeout(7);
        $secs = $inviteTimeout * 24 * 60 * 60;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $nowStr = Carbon::now()->toDateTimeString();

        DB::table('invites')
            ->where(function ($query) use ($dt): void {
                $query->where('time_invited', '<', $dt)
                    ->whereNotNull('time_invited')
                    ->where('invitee', '!=', '');
            })
            ->orWhere(function ($query) use ($nowStr): void {
                $query->where('invitee', '')
                    ->whereNotNull('expired_at')
                    ->where('expired_at', '<', $nowStr);
            })
            ->delete();
    }

    private function deleteRegimages(): void
    {
        DB::table('regimages')->delete();
    }

    // ------------------------------------------------------------------------
    // Inactive user helpers
    // ------------------------------------------------------------------------

    private function disableNoTransferByLastAccess(): void
    {
        $days = (int) SiteConfig::current()->account->deleteNoTransfer(0);
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $maxclass = $this->neverDeleteClass();
        $iniupload = SiteConfig::current()->main->iniUpload(0);

        $query = User::query()
            ->where('parked', 'no')
            ->where('status', User::STATUS_CONFIRMED)
            ->where('class', '<', $maxclass)
            ->where('last_access', '<', $dt)
            ->where('downloaded', 0)
            ->where(function (Builder $q) use ($iniupload): void {
                $q->where('uploaded', 0)->orWhere('uploaded', $iniupload);
            });

        $this->disableUsers($query, 'cleanup.disable_user_no_transfer_alt_last_access_time');
    }

    private function disableNoTransferByRegisterTime(): void
    {
        $days = (int) SiteConfig::current()->account->deleteNoTransferTwo(0);
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $maxclass = $this->neverDeleteClass();
        $iniupload = SiteConfig::current()->main->iniUpload(0);

        $query = User::query()
            ->where('parked', 'no')
            ->where('status', User::STATUS_CONFIRMED)
            ->where('class', '<', $maxclass)
            ->where('added', '<', $dt)
            ->where('downloaded', 0)
            ->where(function (Builder $q) use ($iniupload): void {
                $q->where('uploaded', 0)->orWhere('uploaded', $iniupload);
            });

        $this->disableUsers($query, 'cleanup.disable_user_no_transfer_alt_register_time');
    }

    private function disableNotParked(): void
    {
        $days = (int) SiteConfig::current()->account->deleteUnpacked(0);
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $maxclass = $this->neverDeleteClass();

        $query = User::query()
            ->where('parked', 'no')
            ->where('status', User::STATUS_CONFIRMED)
            ->where('class', '<', $maxclass)
            ->where('last_access', '<', $dt);

        $this->disableUsers($query, 'cleanup.disable_user_not_parked');
    }

    private function disableParked(): void
    {
        $days = (int) SiteConfig::current()->account->deletePacked(0);
        if ($days <= 0) {
            return;
        }

        $secs = $days * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $maxclass = $this->neverDeleteParkedClass();

        $query = User::query()
            ->where('parked', 'yes')
            ->where('status', User::STATUS_CONFIRMED)
            ->where('class', '<', $maxclass)
            ->where('last_access', '<', $dt);

        $this->disableUsers($query, 'cleanup.disable_user_parked');
    }

    private function destroyDisabledAccounts(): void
    {
        $destroyDisabledDays = (int) SiteConfig::current()->account->destroyDisabled(0);
        if ($destroyDisabledDays <= 0) {
            return;
        }

        $secs = $destroyDisabledDays * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        $userRep = new UserRepository;

        User::query()
            ->where('enabled', User::ENABLED_NO)
            ->where('last_access', '<', $dt)
            ->select(['id', 'username', 'lang'])
            ->orderBy('id', 'asc')
            ->chunk(2000, function (Collection $users) use ($userRep): void {
                $userRep->destroy($users, 'cleanup.destroy_disabled_account');
            });
    }

    private function neverDeleteClass(): int
    {
        return min(SiteConfig::current()->account->neverdelete(), (int) User::CLASS_VIP);
    }

    private function neverDeleteParkedClass(): int
    {
        return SiteConfig::current()->account->neverdeletepacked();
    }

    /**
     * @param  Builder<User>  $query
     */
    private function disableUsers(Builder $query, string $reasonKey): void
    {
        $results = $query->where('enabled', User::ENABLED_YES)->get(['id', 'username', 'lang']);
        if ($results->isEmpty()) {
            return;
        }

        $results->load('language');

        $uidArr = [];
        $userBanLogData = [];
        $userModifyLogs = [];

        foreach ($results as $user) {
            $uid = $user->id;
            $enableCacheResult = Cache::get(User::getUserEnableLatelyCacheKey($uid));
            if ($enableCacheResult) {
                Logger::writeWithContext((string) sprintf('user: %s just enable at: %s, skip', $uid, $enableCacheResult), (string) 'info', (bool) false);

                continue;
            }

            $uidArr[] = $uid;
            $reason = Locale::trans($reasonKey, [], $user->locale);

            $userBanLogData[] = [
                'uid' => $uid,
                'username' => $user->username,
                'reason' => $reason,
            ];

            $userModifyLogs[] = [
                'user_id' => $uid,
                'content' => sprintf('[CLEANUP] %s', $reason),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if ($uidArr === []) {
            return;
        }

        User::query()->whereIn('id', $uidArr)->update(['enabled' => User::ENABLED_NO]);
        UserBanLog::query()->insert($userBanLogData);
        UserModifyLog::query()->insert($userModifyLogs);

        Logger::writeWithContext((string) ("[DISABLE_USER]({$reasonKey}): ".implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            Events::publishModel(ModelEventEnum::USER_DISABLED, $uid);
        }
    }

    // ------------------------------------------------------------------------
    // User class helpers
    // ------------------------------------------------------------------------

    private function promotePeasantsToUsers(): void
    {
        $this->peasantToUser(
            (int) SiteConfig::current()->account->psdlfive(0),
            0,
            (float) SiteConfig::current()->account->psratiofive(0),
        );

        $this->peasantToUser(
            (int) SiteConfig::current()->account->psdlfour(0),
            (int) SiteConfig::current()->account->psdlfive(0),
            (float) SiteConfig::current()->account->psratiofour(0),
        );

        $this->peasantToUser(
            (int) SiteConfig::current()->account->psdlthree(0),
            (int) SiteConfig::current()->account->psdlfour(0),
            (float) SiteConfig::current()->account->psratiothree(0),
        );

        $this->peasantToUser(
            (int) SiteConfig::current()->account->psdltwo(0),
            (int) SiteConfig::current()->account->psdlthree(0),
            (float) SiteConfig::current()->account->psratiotwo(0),
        );

        $this->peasantToUser(
            (int) SiteConfig::current()->account->psdlone(0),
            (int) SiteConfig::current()->account->psdltwo(0),
            (float) SiteConfig::current()->account->psratioone(0),
        );
    }

    private function peasantToUser(int $downFloorGb, int $downRoofGb, float $minRatio): void
    {
        if ($downFloorGb <= 0) {
            return;
        }

        $downlimitFloor = $downFloorGb * 1024 * 1024 * 1024;
        $downlimitRoof = $downRoofGb * 1024 * 1024 * 1024;

        $query = User::query()
            ->where('class', User::CLASS_PEASANT)
            ->where('downloaded', '>=', $downlimitFloor);

        if ($downlimitRoof > $downFloorGb) {
            $query->where('downloaded', '<', $downlimitRoof);
        }

        $res = $query->whereRaw('uploaded / downloaded >= ?', [$minRatio])->get(['id']);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);

            UserOps::logModify($uid, 'Leech Warning removed by System.');

            User::query()->where('id', $uid)->update([
                'class' => User::CLASS_USER,
                'leechwarn' => 'no',
                'leechwarnuntil' => null,
            ]);

            DB::table('messages')->insert([
                'sender' => 0,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => Locale::trans('cleanup.msg_low_ratio_warning_removed', [], $locale),
                'msg' => Locale::trans('cleanup.msg_your_ratio_warning_removed', [], $locale),
            ]);

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    private function promoteUsersByClass(): void
    {
        $getInvitesByPromotion = SiteConfig::current()->account->getInvitesByPromotion([]);

        $promotions = [
            User::CLASS_POWER_USER,
            User::CLASS_ELITE_USER,
            User::CLASS_CRAZY_USER,
            User::CLASS_INSANE_USER,
            User::CLASS_VETERAN_USER,
            User::CLASS_EXTREME_USER,
            User::CLASS_ULTIMATE_USER,
            User::CLASS_NEXUS_MASTER,
        ];

        foreach ($promotions as $class) {
            $this->promoteUsers(
                $class,
                SiteConfig::current()->account->promotionDl($class, 0),
                SiteConfig::current()->account->promotionRatio($class, 0.0),
                SiteConfig::current()->account->promotionTime($class, 0),
                (int) ($getInvitesByPromotion[(int) $class] ?? 0),
            );
        }
    }

    private function promoteUsers(string $class, int $downFloorGb, float $minRatio, int $timeWeek, int $addInvite): void
    {
        if ($downFloorGb <= 0) {
            return;
        }

        $limit = $downFloorGb * 1024 * 1024 * 1024;
        $maxdt = date('Y-m-d H:i:s', time() - 86400 * 7 * $timeWeek);

        $minSeedPoints = User::getMinSeedPoints($class);
        if ($minSeedPoints === false) {
            throw new \RuntimeException("class: {$class} can't get min seed points.");
        }

        $oriclass = (int) $class - 1;

        $res = User::query()
            ->where('class', (string) $oriclass)
            ->where('downloaded', '>=', $limit)
            ->where('seed_points', '>=', $minSeedPoints)
            ->whereRaw('uploaded / downloaded >= ?', [$minRatio])
            ->where('added', '<', $maxdt)
            ->get(['id', 'max_class_once']);

        Logger::writeWithContext((string) ('match user count: '.$res->count()), (string) 'info', (bool) false);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);
            $className = \App\Support\User::getUserClassName($class, false, false, false);

            $subject = Locale::trans('cleanup.msg_promoted_to', [], $locale).$className;
            $msg = Locale::trans('cleanup.msg_now_you_are', [], $locale)
                .$className
                .Locale::trans('cleanup.msg_see_faq', [], $locale);

            if ((int) $class <= (int) $arr->max_class_once) {
                Logger::writeWithContext((string) sprintf('user: %s upgrade to class: %s', $uid, $class), (string) 'info', (bool) false);
                User::query()->where('id', $uid)->update(['class' => $class]);
            } else {
                Logger::writeWithContext((string) sprintf('user: %s upgrade to class: %s, and add invites: %s', $uid, $class, $addInvite), (string) 'info', (bool) false);
                User::query()->where('id', $uid)->update([
                    'class' => $class,
                    'max_class_once' => $class,
                    'invites' => DB::raw('invites + '.$addInvite),
                ]);
            }

            DB::table('messages')->insert([
                'sender' => 0,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ]);

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    private function demoteUsersByClass(): void
    {
        $demotions = [
            User::CLASS_NEXUS_MASTER,
            User::CLASS_ULTIMATE_USER,
            User::CLASS_EXTREME_USER,
            User::CLASS_VETERAN_USER,
            User::CLASS_INSANE_USER,
            User::CLASS_CRAZY_USER,
            User::CLASS_ELITE_USER,
            User::CLASS_POWER_USER,
        ];

        foreach ($demotions as $class) {
            $this->demoteUsers($class, SiteConfig::current()->account->demotionRatio($class, 0.0));
        }
    }

    private function demoteUsers(string $class, float $deRatio): void
    {
        if ($deRatio <= 0) {
            return;
        }

        $newclass = (int) $class - 1;

        $res = User::query()
            ->where('class', $class)
            ->whereRaw('uploaded < downloaded * ?', [$deRatio])
            ->get(['id']);

        Logger::writeWithContext((string) ('match user count: '.$res->count()), (string) 'info', (bool) false);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);
            $className = \App\Support\User::getUserClassName($class, false, false, false);
            $newClassName = \App\Support\User::getUserClassName((string) $newclass, false, false, false);

            $subject = Locale::trans('cleanup.msg_demoted_to', [], $locale).$newClassName;
            $msg = Locale::trans('cleanup.msg_demoted_from', [], $locale)
                .$className
                .Locale::trans('cleanup.msg_to', [], $locale)
                .$newClassName
                .Locale::trans('cleanup.msg_because_ratio_drop_below', [], $locale)
                .$deRatio.".\n";

            User::query()->where('id', $uid)->update(['class' => (string) $newclass]);

            DB::table('messages')->insert([
                'sender' => 0,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ]);

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    private function demoteUsersToPeasant(): void
    {
        $configs = [
            [SiteConfig::current()->account->psdlone(0), SiteConfig::current()->account->psratioone(0.0)],
            [SiteConfig::current()->account->psdltwo(0), SiteConfig::current()->account->psratiotwo(0.0)],
            [SiteConfig::current()->account->psdlthree(0), SiteConfig::current()->account->psratiothree(0.0)],
            [SiteConfig::current()->account->psdlfour(0), SiteConfig::current()->account->psratiofour(0.0)],
            [SiteConfig::current()->account->psdlfive(0), SiteConfig::current()->account->psratiofive(0.0)],
        ];

        foreach ($configs as [$downFloorGb, $minRatio]) {
            $this->userToPeasant((int) $downFloorGb, (float) $minRatio);
        }
    }

    private function userToPeasant(int $downFloorGb, float $minRatio): void
    {
        if ($downFloorGb <= 0) {
            return;
        }

        $deletepeasantAccount = (int) SiteConfig::current()->account->deletePeasant(30);
        $length = $deletepeasantAccount * 86400;
        $until = date('Y-m-d H:i:s', time() + $length);
        $downlimitFloor = $downFloorGb * 1024 * 1024 * 1024;

        $res = User::query()
            ->where('class', User::CLASS_USER)
            ->where('downloaded', '>', $downlimitFloor)
            ->whereRaw('uploaded / downloaded < ?', [$minRatio])
            ->get(['id']);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);
            $peasantName = \App\Support\User::getUserClassName(User::CLASS_PEASANT, false, false, false);

            $subject = Locale::trans('cleanup.msg_demoted_to', [], $locale).$peasantName;
            $msg = Locale::trans('cleanup.msg_must_fix_ratio_within', [], $locale)
                .$deletepeasantAccount
                .Locale::trans('cleanup.msg_days_or_get_banned', [], $locale);

            UserOps::logModify($uid, 'Leech Warned by System - Low Ratio.');

            User::query()->where('id', $uid)->update([
                'class' => User::CLASS_PEASANT,
                'leechwarn' => 'yes',
                'leechwarnuntil' => $until,
            ]);

            DB::table('messages')->insert([
                'sender' => 0,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ]);

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    private function banLeechWarningExpired(): void
    {
        $dt = date('Y-m-d H:i:s');

        $results = User::query()
            ->where('class', '<', User::CLASS_VIP)
            ->where('donor', 'no')
            ->where('enabled', User::ENABLED_YES)
            ->where('leechwarn', 'yes')
            ->where('leechwarnuntil', '<', $dt)
            ->get(['id', 'username', 'lang']);

        if ($results->isEmpty()) {
            return;
        }

        $results->load('language');

        $uidArr = [];
        $userBanLogData = [];

        foreach ($results as $user) {
            $uid = $user->id;
            $uidArr[] = $uid;

            $userBanLogData[] = [
                'uid' => $uid,
                'username' => $user->username,
                'reason' => Locale::trans('cleanup.ban_user_with_leech_warning_expired', [], $user->locale),
            ];

            $comment = 'Banned by System because of Leech Warning expired.';
            if (! empty($user->modcomment)) {
                $comment .= ' '.$user->modcomment;
            }
            UserOps::logModify($uid, $comment);
        }

        User::query()->whereIn('id', $uidArr)->update(['enabled' => User::ENABLED_NO]);
        UserBanLog::query()->insert($userBanLogData);

        Logger::writeWithContext((string) ('ban user: '.implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    // ------------------------------------------------------------------------
    // Dead torrent / IP log helpers
    // ------------------------------------------------------------------------

    private function deleteDeadTorrents(): void
    {
        $days = (int) SiteConfig::current()->torrent->delDeadTorrent(0);
        if ($days <= 0) {
            return;
        }

        $length = $days * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);
        $dt = date('Y-m-d H:i:s');

        $res = DB::table('torrents as t')
            ->leftJoin('users as u', 't.owner', '=', 'u.id')
            ->where('t.visible', 'no')
            ->where('t.last_action', '<', $until)
            ->where('t.seeders', 0)
            ->where('t.leechers', 0)
            ->select('t.id', 't.name', 't.owner', 'u.id as uid')
            ->get();

        foreach ($res as $torrent) {
            $arr = (array) $torrent;

            TorrentOps::deleteTorrents((int) $arr['id']);

            if (! empty($arr['uid'])) {
                $locale = Locale::userLocale((int) $arr['owner']);

                DB::table('messages')->insert([
                    'sender' => 0,
                    'receiver' => $arr['owner'],
                    'added' => $dt,
                    'subject' => Locale::trans('cleanup.msg_your_torrent_deleted', [], $locale),
                    'msg' => Locale::trans('cleanup.msg_your_torrent', [], $locale)
                        .'[i]'.$arr['name'].'[/i]'
                        .Locale::trans('cleanup.msg_was_deleted_because_dead', [], $locale),
                ]);

                Log::write("Torrent {$arr['id']} ({$arr['name']}) is deleted by system because of being dead for a long time.", 'normal');
            }
        }
    }

    private function deleteOldIpLogs(): void
    {
        $length = 90 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('iplog')->where('access', '<', $until)->delete();
    }

    private function deleteFailedJobs(): void
    {
        $length = 10 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('failed_jobs')->where('failed_at', '<', $until)->delete();
    }

    /**
     * Priority Class 5: cleanup tasks that run every 15 days.
     */
    public function cleanupClass5(): string
    {
        $this->updateClientPopularity();
        $this->deleteOldSystemMessages();
        $this->deleteOldReadPosts();
        $this->deleteOldCheaters();
        $this->deleteOldShoutbox();
        $this->deleteOldSiteLog();
        $this->lockOldTopics();
        $this->deleteOldReports();

        return 'cleanup class 5';
    }

    // ------------------------------------------------------------------------
    // Class 5 helpers
    // ------------------------------------------------------------------------

    private function updateClientPopularity(): void
    {
        $clientIds = DB::table('agent_allowed_family')->pluck('id');

        foreach ($clientIds as $clientId) {
            $count = DB::table('users')->where('clientselect', $clientId)->count();
            DB::table('agent_allowed_family')->where('id', $clientId)->update(['hits' => $count]);
        }
    }

    private function deleteOldSystemMessages(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('messages')->where('sender', 0)->where('added', '<', $until)->delete();
    }

    private function deleteOldReadPosts(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        $postId = DB::table('posts')
            ->where('added', '<', $until)
            ->orderBy('added', 'desc')
            ->value('id');

        if ($postId) {
            DB::table('users')->where('last_catchup', '<', $postId)->update(['last_catchup' => $postId]);
            DB::table('readposts')->where('lastpostread', '<', $postId)->delete();
        }
    }

    private function deleteOldCheaters(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('cheaters')->where('added', '<', $until)->delete();
    }

    private function deleteOldShoutbox(): void
    {
        $length = 180 * 86400;
        $until = time() - $length;

        DB::table('shoutbox')->where('date', '<', $until)->delete();
    }

    private function deleteOldSiteLog(): void
    {
        $length = 180 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('sitelog')->where('added', '<', $until)->delete();
    }

    private function lockOldTopics(): void
    {
        $length = 365 * 86400;
        $diff = time() - $length;
        $postAddedField = Database::unixTimestampField('posts.added');

        DB::table('topics')
            ->where('sticky', 'no')
            ->whereIn('lastpost', function ($query) use ($postAddedField, $diff): void {
                $query->select('id')->from('posts')->whereRaw("{$postAddedField} < ?", [$diff]);
            })
            ->update(['locked' => 'yes']);
    }

    private function deleteOldReports(): void
    {
        $length = 4 * 7 * 86400;
        $until = date('Y-m-d H:i:s', time() - $length);

        DB::table('reports')
            ->where('dealtwith', 1)
            ->where('added', '<', $until)
            ->delete();
    }
}
