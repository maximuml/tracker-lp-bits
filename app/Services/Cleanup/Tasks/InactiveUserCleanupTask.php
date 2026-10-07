<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Enums\ModelEvent;
use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Repositories\UserCleanupRepository;
use App\Repositories\UserDetailRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Priority Class 4: disable or destroy inactive user accounts.
 */
final class InactiveUserCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly UserModerationRepositoryInterface $userRepository,
        private readonly UserCleanupRepository $userCleanupRepository,
        private readonly UserDetailRepository $userDetailRepository,
    ) {}

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

        $results = $this->userCleanupRepository->listInactiveUsers(false, 'last_access', $dt, $maxclass, $iniupload);

        $this->disableUsers($results, 'cleanup.disable_user_no_transfer_alt_last_access_time');
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

        $results = $this->userCleanupRepository->listInactiveUsers(false, 'added', $dt, $maxclass, $iniupload);

        $this->disableUsers($results, 'cleanup.disable_user_no_transfer_alt_register_time');
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

        $results = $this->userCleanupRepository->listInactiveUsers(false, 'last_access', $dt, $maxclass);

        $this->disableUsers($results, 'cleanup.disable_user_not_parked');
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

        $results = $this->userCleanupRepository->listInactiveUsers(true, 'last_access', $dt, $maxclass);

        $this->disableUsers($results, 'cleanup.disable_user_parked');
    }

    private function destroyDisabledAccounts(): void
    {
        $destroyDisabledDays = (int) SiteConfig::current()->account->destroyDisabled(0);
        if ($destroyDisabledDays <= 0) {
            return;
        }

        $secs = $destroyDisabledDays * 86400;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        $userRep = $this->userRepository;

        $this->userCleanupRepository->chunkDisabledUsersBefore($dt, 2000, function (Collection $users) use ($userRep): void {
            $userRep->destroy($users, 'cleanup.destroy_disabled_account');
        });
    }

    private function neverDeleteClass(): int
    {
        return min(SiteConfig::current()->account->neverdelete(), (int) UserClassEnum::VIP->value);
    }

    private function neverDeleteParkedClass(): int
    {
        return SiteConfig::current()->account->neverdeletepacked();
    }

    /**
     * @param  EloquentCollection<int, User>  $results
     */
    private function disableUsers(EloquentCollection $results, string $reasonKey): void
    {
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

        $this->userCleanupRepository->disableUsers($uidArr);
        $this->userCleanupRepository->insertBanLogs($userBanLogData);
        $this->userDetailRepository->insertUserModifyLogs($userModifyLogs);

        Logger::writeWithContext((string) ("[DISABLE_USER]({$reasonKey}): ".implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            Events::publishModel(ModelEvent::UserDisabled, $uid);
        }
    }

    public function run(): string
    {
        return $this->disableInactiveUsers();
    }
}
