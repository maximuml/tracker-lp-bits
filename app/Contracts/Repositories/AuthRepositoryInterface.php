<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface AuthRepositoryInterface
{
    public function getLoginAttemptsSum(string $ip): int;

    /**
     * @return void
     */
    public function banLoginAttempts(string $ip);

    /**
     * @return void
     */
    public function recordFailedLogin(string $ip, bool $recover);

    /**
     * @return void
     */
    public function updateUserLang(int $userId, int $langId);

    public function countUsers(): int;

    public function countUsersByIp(string $ip): int;

    public function getUserIdByUsername(string $username): ?int;

    public function isIpBanned(int $nip): bool;

    /**
     * @return void
     */
    public function updateUserPasskey(int $userId, string $passkey);

    /**
     * @param  array<string, mixed>  $update
     * @return void
     */
    public function updateLogin(int $userId, array $update);

    public function getAuthVersion(int $userId): ?int;

    public function getPasskeyByUserId(int $userId): ?string;

    /** @return array<string, mixed>|null */
    public function findUserArrayForCookie(int $userId, bool $shouldIgnoreEnabled): ?array;

    public function findUserModelForCookie(int $userId, bool $shouldIgnoreEnabled): ?User;

    public function deleteStaleLoginAttempts(string $before): int;

    public function deleteRegImages(): int;
}
