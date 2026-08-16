<?php

namespace App\Support;

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
        \App\Repositories\UserRepository::logModify($userId, $comment);
    }
}
