<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\UserRepositoryInterface;

/**
 * Legacy user-operation helpers drained out of `include/functions.php`.
 */
final class UserOps
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Record a moderator comment / user modify log entry.
     *
     * Mirrors `writecomment()`.
     */
    public function logModify(int|string $userId, string $comment): void
    {
        $this->userRepository->logModify($userId, $comment);
    }
}
