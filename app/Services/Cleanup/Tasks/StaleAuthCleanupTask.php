<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Priority Class 4: delete unconfirmed accounts, old login attempts, invite
 * codes and regimage records.
 */
final class StaleAuthCleanupTask implements CleanupTask
{
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

    // ------------------------------------------------------------------------
    // Stale auth helpers
    // ------------------------------------------------------------------------

    private function deleteUnconfirmedAccounts(): void
    {
        $signupTimeout = (int) SiteConfig::current()->main->signupTimeout(259200);
        $deadtime = time() - $signupTimeout;

        User::query()
            ->where('status', UserStatus::PENDING->value)
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
            ->where('banned', false)
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

    public function run(): string
    {
        return $this->cleanupStaleAuth();
    }
}
