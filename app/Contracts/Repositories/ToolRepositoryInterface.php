<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface ToolRepositoryInterface
{
    public function backupWeb($method = null, $transfer = false): array;

    public function backupDatabase($transfer = false): array;

    public function backupAll($method = null, $transfer = false): array;

    public function getBackupExportPathDefault(): string;

    public function cronjobBackup($force = false): array|bool;

    public function transfer($filename, $result_code, $setting = null): array;

    public function saveToSftp(array $setting, $filename): string|bool;

    public function sendMail($to, $subject, $body, $exception = false): bool;

    public function getNotificationCount(User $user): array;

    public function listUserClassPermissions($class): array;

    public function listUserAllPermissions($uid): array;

    public function generateUniqueInviteHash(array $hashArr, int $total, int $left, int $deep = 0): array;

    public function removeDuplicateSnatch();

    public function removeDuplicatePeer();

    public function sendAlarmEmail(string $subjectTransKey, array $subjectTransContext, string $msgTransKey, array $msgTransContext);
}
