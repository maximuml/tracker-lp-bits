<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\ModelEventEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Models\UserBanLog;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\UserOps;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 4: promote/demote users and ban leech-warning expiries.
 */
final class UserClassManagementTask implements CleanupTask
{
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
            ->where('class', UserClassEnum::PEASANT->value)
            ->where('downloaded', '>=', $downlimitFloor);

        if ($downlimitRoof > $downFloorGb) {
            $query->where('downloaded', '<', $downlimitRoof);
        }

        $res = $query->whereRaw('uploaded / downloaded >= ?', [$minRatio])->get(['id']);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        $messages = [];
        $uidArr = [];

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);

            UserOps::logModify($uid, 'Leech Warning removed by System.');

            $uidArr[] = $uid;

            $messages[] = [
                'sender' => null,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => Locale::trans('cleanup.msg_low_ratio_warning_removed', [], $locale),
                'msg' => Locale::trans('cleanup.msg_your_ratio_warning_removed', [], $locale),
            ];

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }

        User::query()->whereIn('id', $uidArr)->update([
            'class' => UserClassEnum::USER->value,
            'leechwarn' => 'no',
            'leechwarnuntil' => null,
        ]);

        DB::table('messages')->insert($messages);
    }

    private function promoteUsersByClass(): void
    {
        $getInvitesByPromotion = SiteConfig::current()->account->getInvitesByPromotion([]);

        $promotions = [
            UserClassEnum::POWER_USER->value,
            UserClassEnum::ELITE_USER->value,
            UserClassEnum::CRAZY_USER->value,
            UserClassEnum::INSANE_USER->value,
            UserClassEnum::VETERAN_USER->value,
            UserClassEnum::EXTREME_USER->value,
            UserClassEnum::ULTIMATE_USER->value,
            UserClassEnum::NEXUS_MASTER->value,
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

    private function promoteUsers(int|string $class, int $downFloorGb, float $minRatio, int $timeWeek, int $addInvite): void
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

        $messages = [];

        DB::transaction(function () use ($res, $class, $addInvite, $dt, &$messages): void {
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
                        'invites' => DB::raw(DB::getQueryGrammar()->wrap('invites').' + '.(int) $addInvite), // @phpstan-ignore argument.type
                    ]);
                }

                $messages[] = [
                    'sender' => null,
                    'receiver' => $uid,
                    'added' => $dt,
                    'subject' => $subject,
                    'msg' => $msg,
                ];

                Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
            }
        });

        DB::table('messages')->insert($messages);
    }

    private function demoteUsersByClass(): void
    {
        $demotions = [
            UserClassEnum::NEXUS_MASTER->value,
            UserClassEnum::ULTIMATE_USER->value,
            UserClassEnum::EXTREME_USER->value,
            UserClassEnum::VETERAN_USER->value,
            UserClassEnum::INSANE_USER->value,
            UserClassEnum::CRAZY_USER->value,
            UserClassEnum::ELITE_USER->value,
            UserClassEnum::POWER_USER->value,
        ];

        foreach ($demotions as $class) {
            $this->demoteUsers($class, SiteConfig::current()->account->demotionRatio($class, 0.0));
        }
    }

    private function demoteUsers(int|string $class, float $deRatio): void
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

        $messages = [];
        $uidArr = [];

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

            $uidArr[] = $uid;

            $messages[] = [
                'sender' => null,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ];

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }

        User::query()->whereIn('id', $uidArr)->update(['class' => (string) $newclass]);

        DB::table('messages')->insert($messages);
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
            ->where('class', UserClassEnum::USER->value)
            ->where('downloaded', '>', $downlimitFloor)
            ->whereRaw('uploaded / downloaded < ?', [$minRatio])
            ->get(['id']);

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        $messages = [];
        $uidArr = [];

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);
            $peasantName = \App\Support\User::getUserClassName(UserClassEnum::PEASANT->value, false, false, false);

            $subject = Locale::trans('cleanup.msg_demoted_to', [], $locale).$peasantName;
            $msg = Locale::trans('cleanup.msg_must_fix_ratio_within', [], $locale)
                .$deletepeasantAccount
                .Locale::trans('cleanup.msg_days_or_get_banned', [], $locale);

            UserOps::logModify($uid, 'Leech Warned by System - Low Ratio.');

            $uidArr[] = $uid;

            $messages[] = [
                'sender' => null,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ];

            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }

        User::query()->whereIn('id', $uidArr)->update([
            'class' => UserClassEnum::PEASANT->value,
            'leechwarn' => 'yes',
            'leechwarnuntil' => $until,
        ]);

        DB::table('messages')->insert($messages);
    }

    private function banLeechWarningExpired(): void
    {
        $dt = date('Y-m-d H:i:s');

        $results = User::query()
            ->where('class', '<', UserClassEnum::VIP->value)
            ->where('donor', false)
            ->where('enabled', true)
            ->where('leechwarn', true)
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

        User::query()->whereIn('id', $uidArr)->update(['enabled' => false]);
        UserBanLog::query()->insert($userBanLogData);

        Logger::writeWithContext((string) ('ban user: '.implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            Events::publishModel(ModelEventEnum::USER_UPDATED, $uid);
        }
    }

    public function run(): string
    {
        return $this->manageUserClasses();
    }
}
