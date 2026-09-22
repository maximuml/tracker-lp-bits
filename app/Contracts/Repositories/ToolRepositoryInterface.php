<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface ToolRepositoryInterface
{
    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupWeb($method = null, $transfer = false): array;

    /**
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupDatabase($transfer = false): array;

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupAll($method = null, $transfer = false): array;

    public function getBackupExportPathDefault(): string;

    /**
     * @param  mixed  $force
     * @return bool|array<int|string, mixed>
     */
    public function cronjobBackup($force = false): array|bool;

    /**
     * @param  mixed  $filename
     * @param  mixed  $result_code
     * @param  mixed  $setting
     * @return array<int|string, mixed>
     */
    public function transfer($filename, $result_code, $setting = null): array;

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $filename
     */
    public function saveToSftp(array $setting, $filename): string|bool;

    /**
     * @param  mixed  $to
     * @param  mixed  $subject
     * @param  mixed  $body
     * @param  mixed  $exception
     */
    public function sendMail($to, $subject, $body, $exception = false): bool;

    /**
     * @return array<int|string, mixed>
     */
    public function getNotificationCount(User $user): array;

    /**
     * @param  mixed  $class
     * @return array<int|string, mixed>
     */
    public function listUserClassPermissions($class): array;

    /**
     * @param  mixed  $uid
     * @return array<int|string, mixed>
     */
    public function listUserAllPermissions($uid): array;

    /**
     * @param  array<int|string, mixed>  $hashArr
     * @return array<int|string, mixed>
     */
    public function generateUniqueInviteHash(array $hashArr, int $total, int $left, int $deep = 0): array;

    /**
     * @return mixed
     */
    public function removeDuplicateSnatch();

    /**
     * @return mixed
     */
    public function removeDuplicatePeer();

    /**
     * @param  array<int|string, mixed>  $subjectTransContext
     * @param  array<int|string, mixed>  $msgTransContext
     * @return void
     */
    public function sendAlarmEmail(string $subjectTransKey, array $subjectTransContext, string $msgTransKey, array $msgTransContext);
}
