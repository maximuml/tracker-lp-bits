<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ModtaskRepository
{
    public function confirmUser(int $userId, string $status): void
    {
        User::query()->where('id', $userId)->update([
            'status' => $status,
            'info' => null,
        ]);
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public function getUserArray(int $userId): ?array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return null;
        }

        // passhash is in $hidden on the User model, but the modtask
        // controller needs it for Cache::clearUser() and passkey reset.
        return $user->makeVisible(['passhash'])->toArray();
    }

    public function addFund(int $userId, float $usd, float $cny, string $memo): void
    {
        DB::table('funds')->insert([
            'usd' => $usd,
            'cny' => $cny,
            'user' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'memo' => $memo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateUser(int $userId, array $data): int
    {
        return User::query()->where('id', $userId)->update($data);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function addWarning(int $userId, array $extra): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->increment('timeswarned', 1, $extra);
    }
}
