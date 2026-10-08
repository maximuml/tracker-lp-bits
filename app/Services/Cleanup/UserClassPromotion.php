<?php

declare(strict_types=1);

namespace App\Services\Cleanup;

use App\Enums\ModelEvent;
use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\UserCleanupRepository;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\ModelEventPublisher;
use App\Support\UserOps;
use Illuminate\Support\Facades\DB;

/**
 * Promotion half of UserClassManagementTask: peasant→user recovery and
 * ratio/seed-point class promotions.
 */
final class UserClassPromotion
{
    public function __construct(
        private readonly UserOps $userOps,
        private readonly UserCleanupRepository $userCleanupRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function promotePeasantsToUsers(): void
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

        $res = $this->userCleanupRepository->listPeasantIdsWithHighRatio(
            $downlimitFloor,
            $downlimitRoof > $downFloorGb ? $downlimitRoof : null,
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

            $this->userOps->logModify($uid, 'Leech Warning removed by System.');

            $uidArr[] = $uid;

            $messages[] = [
                'sender' => null,
                'receiver' => $uid,
                'added' => $dt,
                'subject' => Locale::trans('cleanup.msg_low_ratio_warning_removed', [], $locale),
                'msg' => Locale::trans('cleanup.msg_your_ratio_warning_removed', [], $locale),
            ];

            ModelEventPublisher::publish(ModelEvent::UserUpdated, $uid);
        }

        $this->userCleanupRepository->updateWhereInIds($uidArr, [
            'class' => UserClassEnum::USER->value,
            'leechwarn' => false,
            'leechwarnuntil' => null,
        ]);

        $this->messageRepository->insertMessages($messages);
    }

    public function promoteUsersByClass(): void
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

        $res = $this->userCleanupRepository->listPromotionCandidates(
            $oriclass,
            $limit,
            $minSeedPoints,
            $minRatio,
            $maxdt,
        );

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
                    $this->userCleanupRepository->updateWhereInIds([$uid], ['class' => $class]);
                } else {
                    Logger::writeWithContext((string) sprintf('user: %s upgrade to class: %s, and add invites: %s', $uid, $class, $addInvite), (string) 'info', (bool) false);
                    $this->userCleanupRepository->updateWhereInIds([$uid], [
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

                ModelEventPublisher::publish(ModelEvent::UserUpdated, $uid);
            }
        });

        $this->messageRepository->insertMessages($messages);
    }
}
