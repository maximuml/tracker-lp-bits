<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Repositories\UserAccountRepository;

/**
 * Parameter object bundling the data-access dependencies of the
 * registration flow — keeps RegistrationService's constructor within the
 * RepositorySizeTest dependency cap.
 */
final readonly class RegistrationRepositories
{
    public function __construct(
        public UsercpLookupRepositoryInterface $usercpLookup,
        public UserModerationRepositoryInterface $userModeration,
        public UserAccountRepository $userAccount,
    ) {}
}
