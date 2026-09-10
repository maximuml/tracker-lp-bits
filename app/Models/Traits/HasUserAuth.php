<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\Permission\RoutePermissionEnum;

/**
 * Authentication and permission helpers for the User model.
 */
trait HasUserAuth
{
    /** @param string $name */
    public function acceptNotification($name): bool
    {
        return $this->original['notifs'] === null || str_contains((string) $this->notifs, "[{$name}]");
    }

    public function tokenCan(string $ability): bool
    {
        if ($this->accessToken === null) {
            return false;
        }

        $routePermission = RoutePermissionEnum::tryFrom($ability);
        if ($routePermission !== null) {
            $legacyPermission = $routePermission->toPermissionEnum();
            if ($legacyPermission === null) {
                return $this->accessToken->can($ability);
            }

            return Permission::can($legacyPermission, $this)
                && $this->accessToken->can($ability);
        }

        $legacyPermission = PermissionEnum::tryFrom($ability);
        if ($legacyPermission !== null) {
            return Permission::can($legacyPermission, $this)
                && $this->accessToken->can($ability);
        }

        return $this->accessToken->can($ability);
    }
}
