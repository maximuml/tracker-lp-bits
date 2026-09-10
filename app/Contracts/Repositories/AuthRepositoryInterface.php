<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface AuthRepositoryInterface
{
    public function getLoginAttemptsSum(string $ip): int;

    public function banLoginAttempts(string $ip);

    public function recordFailedLogin(string $ip, bool $recover);

    public function updateUserLang(int $userId, int $langId);

    public function countUsers(): int;

    public function countUsersByIp(string $ip): int;

    public function getUserIdByUsername(string $username): ?int;

    public function isIpBanned(int $nip): bool;

    public function updateUserPasskey(int $userId, string $passkey);

    public function updateLogin(int $userId, array $update);

    public function getPasskeyByUserId(int $userId): ?string;

    public function findUserArrayForCookie(int $userId, bool $shouldIgnoreEnabled): ?array;

    public function findUserModelForCookie(int $userId, bool $shouldIgnoreEnabled): ?User;
}
