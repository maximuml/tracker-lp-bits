<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ModtaskRepository
{
    public function confirmUser(int $userId, string $status): void
    {
        User::query()->where('id', $userId)->update([
            'status' => UserStatus::fromStringSafe($status)->value,
            'info' => null,
        ]);
    }

    public function getUser(int $userId): ?User
    {
        return User::query()->find($userId);
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
}
