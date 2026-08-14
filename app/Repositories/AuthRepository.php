<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class AuthRepository extends BaseRepository
{
    public function getLoginAttemptsSum(string $ip): int
    {
        return (int) NexusDB::table('loginattempts')->where('ip', $ip)->sum('attempts');
    }

    public function banLoginAttempts(string $ip): void
    {
        NexusDB::table('loginattempts')->where('ip', $ip)->update(['banned' => 'yes']);
    }

    public function recordFailedLogin(string $ip, bool $recover): void
    {
        $count = (int) NexusDB::table('loginattempts')->where('ip', $ip)->count();

        if ($count === 0) {
            NexusDB::table('loginattempts')->insert([
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => 1,
            ]);
        } else {
            NexusDB::table('loginattempts')
                ->where('ip', $ip)
                ->update(['attempts' => NexusDB::raw('attempts + 1')]);
        }

        if ($recover) {
            NexusDB::table('loginattempts')->where('ip', $ip)->update(['type' => 'recover']);
        }
    }

    public function updateUserLang(int $userId, int $langId): void
    {
        NexusDB::table('users')->where('id', $userId)->update(['lang' => $langId]);
    }

    public function countUsers(): int
    {
        return (int) NexusDB::table('users')->count();
    }

    public function countUsersByIp(string $ip): int
    {
        return (int) NexusDB::table('users')->where('ip', $ip)->count();
    }

    public function getUserIdByUsername(string $username): ?int
    {
        $id = NexusDB::table('users')
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function isIpBanned(int $nip): bool
    {
        return NexusDB::table('bans')
            ->where('first', '<=', $nip)
            ->where('last', '>=', $nip)
            ->exists();
    }

    public function updateUserPasskey(int $userId, string $passkey): void
    {
        NexusDB::table('users')->where('id', $userId)->update(['passkey' => $passkey]);
    }
}
