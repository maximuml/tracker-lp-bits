<?php

namespace App\Repositories;

use App\Models\User;
use Carbon\Carbon;
use Nexus\Database\NexusDB;

class ModtaskRepository
{
    /**
     * @param  int  $userId
     * @param  string  $status
     */
    public static function confirmUser(int $userId, string $status): void
    {
        User::query()->where('id', $userId)->update([
            'status' => $status,
            'info' => null,
        ]);
    }

    /**
     * @param  int  $userId
     * @return  ?array<int|string, mixed>
     */
    public static function getUserArray(int $userId): ?array
    {
        $user = User::query()->find($userId);

        return $user === null ? null : $user->toArray();
    }

    /**
     * @param  int  $userId
     * @param  float  $usd
     * @param  float  $cny
     * @param  string  $memo
     */
    public static function addFund(int $userId, float $usd, float $cny, string $memo): void
    {
        NexusDB::table('funds')->insert([
            'usd' => $usd,
            'cny' => $cny,
            'user' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'memo' => $memo,
        ]);
    }

    /**
     * @param  int  $userId
     * @param  array<int|string, mixed>  $data
     */
    public static function updateUser(int $userId, array $data): int
    {
        return User::query()->where('id', $userId)->update($data);
    }

    /**
     * @param  int  $userId
     * @param  array<int|string, mixed>  $extra
     */
    public static function addWarning(int $userId, array $extra): void
    {
        NexusDB::table('users')
            ->where('id', $userId)
            ->increment('timeswarned', 1, $extra);
    }
}
