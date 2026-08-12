<?php

namespace App\Support;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Repositories\ToolRepository;

/**
 * Legacy permission helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `user_can()`, `assert_has_permission()`, `ability()` and
 * `has_role_work_seeding()`.
 */
final class Permissions
{
    /**
     * @var array<string, array<int, bool>>
     */
    private static array $userCanCache = [];

    private static int $sequence = 0;

    /**
     * Check whether a user has a named permission.
     *
     * Mirrors `user_can()`. With `$fail = true` an unauthenticated user or a
     * missing permission either calls the legacy `stderr()` handler (in the
     * legacy UI context) or throws `InsufficientPermissionException`.
     */
    public static function userCan(string $permission, bool $fail = false, int $uid = 0): bool
    {
        $log = "permission: $permission, fail: " . ($fail ? 'true' : 'false') . ", user: $uid";

        if ($uid == 0) {
            $uid = (int) \App\Support\UserDisplay::currentId();
            $log .= ", set current uid: $uid";
        }

        if ($uid <= 0) {
            if ($fail) {
                goto fail;
            }
            Logger::writeWithContext("$log, unauthenticated, false");
            return false;
        }

        if (!$fail && isset(self::$userCanCache[$permission][$uid])) {
            return self::$userCanCache[$permission][$uid];
        }

        $userInfo = \App\Support\UserDisplay::row($uid);
        $class = $userInfo['class'] ?? '';
        $log .= ", userClass: $class";

        if ($class == User::CLASS_STAFF_LEADER) {
            Logger::writeWithContext("$log, CLASS_STAFF_LEADER, true");
            self::$userCanCache[$permission][$uid] = true;
            return true;
        }

        $userAllPermissions = ToolRepository::listUserAllPermissions($uid);
        $result = isset($userAllPermissions[$permission]);

        if (self::$sequence === 0) {
            self::$sequence++;
            $log .= ', userAllPermissions: ' . Json::encode($userAllPermissions);
        }

        $log .= ", result: " . ($result ? 'true' : 'false');

        if (!$fail || $result) {
            Logger::writeWithContext($log);
            self::$userCanCache[$permission][$uid] = $result;
            return $result;
        }

        fail:
        Logger::writeWithContext("$log, [FAIL]");
        if (defined('IN_NEXUS') && IN_NEXUS && !(defined('IN_TRACKER') && IN_TRACKER)) {
            $lang_functions = SupportContext::getLangFunctions();
            $requireClass = \App\Support\Config\SiteConfig::current()->authority->permission($permission);
            if (isset(User::$classes[$requireClass])) {
                \App\Support\LegacyResponse::abort($lang_functions['std_sorry'], $lang_functions['std_permission_denied_only'] . \App\Support\UserClass::name($requireClass, false, true, true) . sprintf($lang_functions['std_or_above_can_view'], \App\Support\Config\SiteConfig::current()->basic->siteName()), false);
            } else {
                \App\Support\LegacyResponse::abort($lang_functions['std_error'], $lang_functions['std_permission_denied']);
            }
        }

        throw new InsufficientPermissionException();
    }

    public static function assertHasPermission(bool $permissionCheckResult): void
    {
        if (!$permissionCheckResult) {
            throw new InsufficientPermissionException();
        }
    }

    /**
     * Check whether a user has a permission, resolving the permission string to a
     * typed enum and the user id to the current request user when needed.
     *
     * Backs the legacy `user_can()` helper.
     */
    public static function canWithContext(string|PermissionEnum $permission, bool $fail = false, int $uid = 0): bool
    {
        $enum = $permission instanceof PermissionEnum ? $permission : PermissionEnum::tryFrom($permission);
        if ($enum === null) {
            Logger::writeWithContext("Unknown permission string: $permission", 'error');
            if ($fail) {
                self::assertHasPermission(false);
            }
            return false;
        }

        if ($uid <= 0) {
            $uid = (int) UserDisplay::currentId();
        }
        if ($uid <= 0) {
            if ($fail) {
                self::assertHasPermission(false);
            }
            return false;
        }

        $user = User::find($uid);
        if (!$user) {
            if ($fail) {
                self::assertHasPermission(false);
            }
            return false;
        }

        $result = Permission::can($enum, $user);
        if ($fail && !$result) {
            self::assertHasPermission(false);
        }

        return $result;
    }

    public static function abilityLabel(\App\Enums\Permission\RoutePermissionEnum $permission): string
    {
        return sprintf('ability:%s', $permission->value);
    }

    public static function hasRoleWorkSeeding(int $uid): mixed
    {
        $result = Hooks::applyFilter('user_has_role_work_seeding', false, $uid);
        Logger::writeWithContext("uid: $uid, result: " . ($result ? 'true' : 'false'));
        return $result;
    }
}
