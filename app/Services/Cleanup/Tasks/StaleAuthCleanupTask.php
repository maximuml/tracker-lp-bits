<?php

declare(strict_types=1);

namespace App\Services\Cleanup\Tasks;

use App\Contracts\Repositories\AuthRepositoryInterface;
use App\Repositories\InviteRepository;
use App\Repositories\UserCleanupRepository;
use App\Services\Cleanup\Contracts\CleanupTask;
use App\Support\Config\SiteConfig;
use Carbon\Carbon;

/**
 * Priority Class 4: delete unconfirmed accounts, old login attempts, invite
 * codes and regimage records.
 */
final class StaleAuthCleanupTask implements CleanupTask
{
    public function __construct(
        private readonly UserCleanupRepository $userCleanupRepository,
        private readonly AuthRepositoryInterface $authRepository,
        private readonly InviteRepository $inviteRepository,
    ) {}

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

        $this->userCleanupRepository->deleteStaleUnconfirmedAccounts($deadtime);
    }

    private function deleteOldLoginAttempts(): void
    {
        $secs = 12 * 60 * 60;
        $dt = date('Y-m-d H:i:s', time() - $secs);

        $this->authRepository->deleteStaleLoginAttempts($dt);
    }

    private function deleteOldInviteCodes(): void
    {
        $inviteTimeout = (int) SiteConfig::current()->main->inviteTimeout(7);
        $secs = $inviteTimeout * 24 * 60 * 60;
        $dt = date('Y-m-d H:i:s', time() - $secs);
        $nowStr = Carbon::now()->toDateTimeString();

        $this->inviteRepository->deleteExpiredCodes($dt, $nowStr);
    }

    private function deleteRegimages(): void
    {
        $this->authRepository->deleteRegImages();
    }

    public function run(): string
    {
        return $this->cleanupStaleAuth();
    }
}
