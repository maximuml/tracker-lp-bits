<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\ModelEvent;
use App\Enums\UserClass as UserClassEnum;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Services\Cleanup\UserClassPromotion;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\ModelEventPublisher;
use App\Support\User;
use App\Support\UserOps;

/**
 * Priority Class 4: promote/demote users and ban leech-warning expiries.
 * Promotions live in UserClassPromotion.
 */
final class UserClassManagementTask implements CleanupTask
{
    public function __construct(
        private readonly UserClassPromotion $promotion,
        private readonly UserOps $userOps,
        private readonly UserCleanupRepository $userCleanupRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    /**
     * Priority Class 4: promote/demote users and ban leech-warning expiries.
     */
    public function manageUserClasses(): string
    {
        $this->promotion->promotePeasantsToUsers();
        $this->promotion->promoteUsersByClass();
        $this->demoteUsersByClass();
        $this->demoteUsersToPeasant();
        $this->banLeechWarningExpired();

        return 'manage user classes';
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

        $res = $this->userCleanupRepository->listUserIdsByClassRatioBelow((int) $class, $deRatio);

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
            $className = User::getUserClassName($class, false, false, false);
            $newClassName = User::getUserClassName((string) $newclass, false, false, false);

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

            ModelEventPublisher::publish(ModelEvent::UserUpdated, $uid);
        }

        $this->userCleanupRepository->updateWhereInIds($uidArr, ['class' => (string) $newclass]);

        $this->messageRepository->insertMessages($messages);
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

        $res = $this->userCleanupRepository->listUserIdsWithLowRatio(
            UserClassEnum::USER->value,
            $downlimitFloor,
            $minRatio,
        );

        if ($res->isEmpty()) {
            return;
        }

        $dt = date('Y-m-d H:i:s');

        $messages = [];
        $uidArr = [];

        foreach ($res as $arr) {
            $uid = $arr->id;
            $locale = Locale::userLocale($uid);
            $peasantName = User::getUserClassName(UserClassEnum::PEASANT->value, false, false, false);

            $subject = Locale::trans('cleanup.msg_demoted_to', [], $locale).$peasantName;
            $msg = Locale::trans('cleanup.msg_must_fix_ratio_within', [], $locale)
                .$deletepeasantAccount
                .Locale::trans('cleanup.msg_days_or_get_banned', [], $locale);

            $this->userOps->logModify($uid, 'Leech Warned by System - Low Ratio.');

            $uidArr[] = $uid;

            $messages[] = [
                'sender' => null,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => $subject,
                'msg' => $msg,
            ];

            ModelEventPublisher::publish(ModelEvent::UserUpdated, $uid);
        }

        $this->userCleanupRepository->updateWhereInIds($uidArr, [
            'class' => UserClassEnum::PEASANT->value,
            'leechwarn' => true,
            'leechwarnuntil' => $until,
        ]);

        $this->messageRepository->insertMessages($messages);
    }

    private function banLeechWarningExpired(): void
    {
        $dt = date('Y-m-d H:i:s');

        $results = $this->userCleanupRepository->listExpiredLeechWarnedUsers($dt, UserClassEnum::VIP->value);

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
            $this->userOps->logModify($uid, $comment);
        }

        $this->userCleanupRepository->disableUsers($uidArr);
        $this->userCleanupRepository->insertBanLogs($userBanLogData);

        Logger::writeWithContext((string) ('ban user: '.implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            ModelEventPublisher::publish(ModelEvent::UserUpdated, $uid);
        }
    }

    public function run(): string
    {
        return $this->manageUserClasses();
    }
}
