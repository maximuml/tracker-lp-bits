<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\ModelEventEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Repositories\UserRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Priority Class 4: disable or destroy inactive user accounts.
 */
final class InactiveUserCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
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

        $query = User::query()
            ->where('parked', 'no')
            ->where('status', UserStatus::CONFIRMED->value)
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
            ->where('status', UserStatus::CONFIRMED->value)
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
            ->where('status', UserStatus::CONFIRMED->value)
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
            ->where('status', UserStatus::CONFIRMED->value)
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

        $userRep = $this->userRepository;

        User::query()
            ->where('enabled', false)
            ->where('last_access', '<', $dt)
            ->select(['id', 'username', 'lang'])
            ->orderBy('id', 'asc')
            ->chunk(2000, function (Collection $users) use ($userRep): void {
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
     * @param  Builder<User>  $query
     */
    private function disableUsers(Builder $query, string $reasonKey): void
    {
        $results = $query->where('enabled', true)->get(['id', 'username', 'lang']);
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

        User::query()->whereIn('id', $uidArr)->update(['enabled' => false]);
        UserBanLog::query()->insert($userBanLogData);
        UserModifyLog::query()->insert($userModifyLogs);

        Logger::writeWithContext((string) ("[DISABLE_USER]({$reasonKey}): ".implode(', ', $uidArr)), (string) 'info', (bool) false);

        foreach ($uidArr as $uid) {
            Events::publishModel(ModelEventEnum::USER_DISABLED, $uid);
        }
    }

    public function run(): string
    {
        return $this->disableInactiveUsers();
    }
}
