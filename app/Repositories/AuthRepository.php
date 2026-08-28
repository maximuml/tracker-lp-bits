<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthRepository extends BaseRepository
{
    public function getLoginAttemptsSum(string $ip): int
    {
        return (int) DB::table('loginattempts')->where('ip', $ip)->sum('attempts');
    }

    public function banLoginAttempts(string $ip): void
    {
        DB::table('loginattempts')->where('ip', $ip)->update(['banned' => 'yes']);
    }

    public function recordFailedLogin(string $ip, bool $recover): void
    {
        $count = (int) DB::table('loginattempts')->where('ip', $ip)->count();

        if ($count === 0) {
            DB::table('loginattempts')->insert([
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => 1,
            ]);
        } else {
            DB::table('loginattempts')
                ->where('ip', $ip)
                ->increment('attempts');
        }

        if ($recover) {
            DB::table('loginattempts')->where('ip', $ip)->update(['type' => 'recover']);
        }
    }

    public function updateUserLang(int $userId, int $langId): void
    {
        DB::table('users')->where('id', $userId)->update(['lang' => $langId]);
    }

    public function countUsers(): int
    {
        return (int) DB::table('users')->count();
    }

    public function countUsersByIp(string $ip): int
    {
        return (int) DB::table('users')->where('ip', $ip)->count();
    }

    public function getUserIdByUsername(string $username): ?int
    {
        $id = DB::table('users')
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function isIpBanned(int $nip): bool
    {
        return DB::table('bans')
            ->where('first', '<=', $nip)
            ->where('last', '>=', $nip)
            ->exists();
    }

    public function updateUserPasskey(int $userId, string $passkey): void
    {
        DB::table('users')->where('id', $userId)->update(['passkey' => $passkey]);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function updateLogin(int $userId, array $update): void
    {
        User::query()->where('id', $userId)->update($update);
    }

    public static function getPasskeyByUserId(int $userId): ?string
    {
        $user = User::query()->where('id', $userId)->first(['id', 'passkey']);

        return $user?->passkey;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findUserArrayForCookie(int $userId, bool $shouldIgnoreEnabled): ?array
    {
        $query = DB::table('users')
            ->where('id', $userId)
            ->where('status', 'confirmed');
        if (! $shouldIgnoreEnabled) {
            $query->where('enabled', 'yes');
        }
        $result = $query->first();

        return $result ? array_merge((array) $result, array_values((array) $result)) : null;
    }

    public static function findUserModelForCookie(int $userId, bool $shouldIgnoreEnabled): ?User
    {
        $row = User::query()->find($userId);
        if (! $row) {
            return null;
        }
        $checkFields = ['status'];
        if (! $shouldIgnoreEnabled) {
            $checkFields[] = 'enabled';
        }
        $row->checkIsNormal($checkFields);

        return $row;
    }
}
