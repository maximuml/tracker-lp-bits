<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\UserRepositoryInterface;

/**
 * Legacy user-operation helpers drained out of `include/functions.php`.
 */
final class UserOps
{
    /**
     * Record a moderator comment / user modify log entry.
     *
     * Mirrors `writecomment()`.
     */
    public static function logModify(int|string $userId, string $comment): void
    {
        app(UserRepositoryInterface::class)->logModify($userId, $comment);
    }
}
