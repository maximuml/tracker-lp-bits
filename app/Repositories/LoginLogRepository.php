<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\LoginLog;

/**
 * Login-history rows — currently just the notify-job pair: fetch the
 * triggering row plus the immediately previous login for the same user.
 */
final class LoginLogRepository
{
    public function findOrFailById(int $id): LoginLog
    {
        return LoginLog::query()->where('id', $id)->firstOrFail();
    }

    /**
     * The login recorded right before `$beforeId` for one user.
     */
    public function findPreviousByUid(int $uid, int $beforeId): ?LoginLog
    {
        /** @var LoginLog|null */
        return LoginLog::query()
            ->where('uid', $uid)
            ->where('id', '<', $beforeId)
            ->orderBy('id', 'desc')
            ->first();
    }
}
