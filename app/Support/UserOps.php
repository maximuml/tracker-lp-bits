<?php

namespace App\Support;

use App\Repositories\UserRepository;

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
        UserRepository::logModify($userId, $comment);
    }
}
