<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Permission;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Enums\Permission\RoutePermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\State\PermissionsState;

/**
 * Legacy permission helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `user_can()`, `assert_has_permission()`, `ability()` and
 * `has_role_work_seeding()`.
 */
final class Permissions
{
    public static function resetState(): void
    {
        PermissionsState::instance()->reset();
    }

    /**
     * Check whether a user has a named permission.
     *
     * Mirrors `user_can()`. With `$fail = true` an unauthenticated user or a
     * missing permission either calls the legacy `stderr()` handler (in the
     * legacy UI context) or throws `InsufficientPermissionException`.
     */
    public static function userCan(string $permission, bool $fail = false, int $uid = 0): bool
    {
        $log = "permission: $permission, fail: ".($fail ? 'true' : 'false').", user: $uid";

        if ($uid == 0) {
            $uid = (int) UserDisplay::currentId();
            $log .= ", set current uid: $uid";
        }

        if ($uid <= 0) {
            if ($fail) {
                return self::permissionFail($log, $permission);
            }
            Logger::writeWithContext("$log, unauthenticated, false");

            return false;
        }

        if (! $fail && isset(PermissionsState::instance()->userCanCache[$permission][$uid])) {
            return PermissionsState::instance()->userCanCache[$permission][$uid];
        }

        $userInfo = UserDisplay::row($uid);
        $class = $userInfo['class'] ?? '';
        $log .= ", userClass: $class";

        if ($class == UserClassEnum::STAFFLEADER->value) {
            Logger::writeWithContext("$log, CLASS_STAFF_LEADER, true");
            PermissionsState::instance()->userCanCache[$permission][$uid] = true;

            return true;
        }

        $userAllPermissions = app(ToolRepositoryInterface::class)->listUserAllPermissions($uid);
        $result = isset($userAllPermissions[$permission]);

        if (PermissionsState::instance()->sequence === 0) {
            PermissionsState::instance()->sequence++;
            $log .= ', userAllPermissions: '.Json::encode($userAllPermissions);
        }

        $log .= ', result: '.($result ? 'true' : 'false');

        if (! $fail || $result) {
            Logger::writeWithContext($log);
            PermissionsState::instance()->userCanCache[$permission][$uid] = $result;

            return $result;
        }

        return self::permissionFail($log, $permission);
    }

    /**
     * Handle permission failure: log and abort with legacy response.
     */
    private static function permissionFail(string $log, string $permission = ''): bool
    {
        Logger::writeWithContext("$log, [FAIL]");
        $runtime = RuntimeContext::instance();
        if ($runtime->isLegacy() && ! $runtime->isTracker()) {
            $requireClass = SiteConfig::current()->authority->permission($permission);
            if ($requireClass !== null && isset(User::$classes[$requireClass])) {
                PageResponses::abort(__('functions.std_sorry'), __('functions.std_permission_denied_only').UserClass::name($requireClass, false, true, true).__('functions.std_or_above_can_view').view('components.permission-faq-note', ['siteName' => SiteConfig::current()->basic->siteName()])->render(), false);
            } else {
                PageResponses::abort(__('functions.std_error'), __('functions.std_permission_denied'));
            }
        }

        throw new InsufficientPermissionException;
    }

    public static function assertHasPermission(bool $permissionCheckResult): void
    {
        if (! $permissionCheckResult) {
            throw new InsufficientPermissionException;
        }
    }

    public static function abilityLabel(RoutePermissionEnum $permission): string
    {
        return sprintf('ability:%s', $permission->value);
    }

    public static function hasRoleWorkSeeding(int $uid): bool
    {
        Logger::writeWithContext("uid: $uid, result: false");

        return false;
    }
}
