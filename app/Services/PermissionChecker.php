<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Permissions;

/**
 * Injectable front for `Permissions::userCan()` — controllers take this
 * instead of the Support static so permission checks are mockable and the
 * call shape survives a future instance-based implementation.
 */
class PermissionChecker
{
    public function userCan(string $permission, bool $fail = false, int $uid = 0): bool
    {
        return Permissions::userCan($permission, $fail, $uid);
    }

    public function hasRoleWorkSeeding(int $uid): bool
    {
        return Permissions::hasRoleWorkSeeding($uid);
    }
}
