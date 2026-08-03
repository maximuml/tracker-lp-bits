<?php

namespace App\Support;

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
            $uid = (int) \get_user_id();
            $log .= ", set current uid: $uid";
        }

        if ($uid <= 0) {
            if ($fail) {
                goto fail;
            }
            \do_log("$log, unauthenticated, false");
            return false;
        }

        if (!$fail && isset(self::$userCanCache[$permission][$uid])) {
            return self::$userCanCache[$permission][$uid];
        }

        $userInfo = \get_user_row($uid);
        $class = $userInfo['class'] ?? '';
        $log .= ", userClass: $class";

        if ($class == User::CLASS_STAFF_LEADER) {
            \do_log("$log, CLASS_STAFF_LEADER, true");
            self::$userCanCache[$permission][$uid] = true;
            return true;
        }

        $userAllPermissions = ToolRepository::listUserAllPermissions($uid);
        $result = isset($userAllPermissions[$permission]);

        if (self::$sequence === 0) {
            self::$sequence++;
            $log .= ', userAllPermissions: ' . \nexus_json_encode($userAllPermissions);
        }

        $log .= ", result: " . ($result ? 'true' : 'false');

        if (!$fail || $result) {
            \do_log($log);
            self::$userCanCache[$permission][$uid] = $result;
            return $result;
        }

        fail:
        \do_log("$log, [FAIL]");
        if (defined('IN_NEXUS') && IN_NEXUS && !(defined('IN_TRACKER') && IN_TRACKER)) {
            $lang_functions = SupportContext::getLangFunctions();
            $requireClass = \get_setting("authority.$permission");
            if (isset(User::$classes[$requireClass])) {
                \stderr(
                    $lang_functions['std_sorry'],
                    $lang_functions['std_permission_denied_only'] . \get_user_class_name($requireClass, false, true, true) . sprintf($lang_functions['std_or_above_can_view'], \App\Models\Setting::getSiteName()),
                    false,
                );
            } else {
                \stderr($lang_functions['std_error'], $lang_functions['std_permission_denied']);
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

    public static function abilityLabel(\App\Enums\Permission\RoutePermissionEnum $permission): string
    {
        return sprintf('ability:%s', $permission->value);
    }

    public static function hasRoleWorkSeeding(int $uid): mixed
    {
        $result = \apply_filter('user_has_role_work_seeding', false, $uid);
        \do_log("uid: $uid, result: " . ($result ? 'true' : 'false'));
        return $result;
    }
}
