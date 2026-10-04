<?php

declare(strict_types=1);

namespace App\Services\Ajax;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\AttendanceRepository;
use App\Repositories\ExamUserRepository;
use App\Repositories\UserAccountRepository;

/**
 * Parameter object bundling the user-domain repository dependencies of
 * AjaxService — keeps its constructor within the RepositorySizeTest
 * dependency cap.
 */
final readonly class AjaxUserRepositories
{
    public function __construct(
        public AttendanceRepository $attendance,
        public UserRepositoryInterface $users,
        public UserModerationRepositoryInterface $userModeration,
        public ExamUserRepository $examUsers,
        public UserAccountRepository $userAccount,
    ) {}
}
