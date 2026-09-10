<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Models\User;
use App\Services\BackupService;
use App\Services\BackupTransferService;
use App\Services\ToolCleanupService;
use App\Services\ToolMaintenanceService;

class ToolRepository extends BaseRepository implements ToolRepositoryInterface
{
    public const BACKUP_EXCLUDES = BackupService::BACKUP_EXCLUDES;

    public const BACKUP_RETENTION_COUNT_DEFAULT = BackupService::BACKUP_RETENTION_COUNT_DEFAULT;

    public function __construct(
        private BackupService $backupService,
        private BackupTransferService $transferService,
        private ToolMaintenanceService $maintenanceService,
        private ToolCleanupService $cleanupService,
    ) {}

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupWeb($method = null, $transfer = false): array
    {
        return $this->backupService->backupWeb($method, $transfer);
    }

    /**
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupDatabase($transfer = false): array
    {
        return $this->backupService->backupDatabase($transfer);
    }

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupAll($method = null, $transfer = false): array
    {
        return $this->backupService->backupAll($method, $transfer);
    }

    public function getBackupExportPathDefault(): string
    {
        return $this->backupService->getBackupExportPathDefault();
    }

    /**
     * do backup cronjob
     *
     * @param  mixed  $force
     * @return bool|array<int|string, mixed>
     */
    public function cronjobBackup($force = false): bool|array
    {
        return $this->backupService->cronjobBackup($force);
    }

    /**
     * @param  mixed  $filename
     * @param  mixed  $result_code
     * @param  mixed  $setting
     * @return array<int|string, mixed>
     */
    public function transfer($filename, $result_code, $setting = null): array
    {
        return $this->transferService->transfer($filename, $result_code, $setting);
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $filename
     */
    public function saveToSftp(array $setting, $filename): bool|string
    {
        return $this->transferService->saveToSftp($setting, $filename);
    }

    /**
     * @param  mixed  $to
     * @param  mixed  $subject
     * @param  mixed  $body
     * @param  mixed  $exception
     */
    public function sendMail($to, $subject, $body, $exception = false): bool
    {
        return $this->maintenanceService->sendMail($to, $subject, $body, $exception);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getNotificationCount(User $user): array
    {
        return $this->maintenanceService->getNotificationCount($user);
    }

    /**
     * @param  mixed  $class
     * @return array<int|string, mixed>
     */
    public function listUserClassPermissions($class): array
    {
        return $this->maintenanceService->listUserClassPermissions($class);
    }

    /**
     * @param  mixed  $uid
     * @return array<int|string, mixed>
     */
    public function listUserAllPermissions($uid): array
    {
        return $this->maintenanceService->listUserAllPermissions($uid);
    }

    /**
     * @param  array<int|string, mixed>  $hashArr
     * @return array<int|string, mixed>
     */
    public function generateUniqueInviteHash(array $hashArr, int $total, int $left, int $deep = 0): array
    {
        return $this->maintenanceService->generateUniqueInviteHash($hashArr, $total, $left, $deep);
    }

    /** @return  mixed */
    public function removeDuplicateSnatch()
    {
        return $this->cleanupService->removeDuplicateSnatch();
    }

    /** @return  mixed */
    public function removeDuplicatePeer()
    {
        return $this->cleanupService->removeDuplicatePeer();
    }

    /**
     * @param  array<int|string, mixed>  $subjectTransContext
     * @param  array<int|string, mixed>  $msgTransContext
     */
    public function sendAlarmEmail(string $subjectTransKey, array $subjectTransContext, string $msgTransKey, array $msgTransContext): void
    {
        $this->maintenanceService->sendAlarmEmail($subjectTransKey, $subjectTransContext, $msgTransKey, $msgTransContext);
    }
}
