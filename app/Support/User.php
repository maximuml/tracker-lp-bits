<?php

namespace App\Support;

/**
 * User/permission helpers extracted from `include/functions.php`.
 *
 * Backs the legacy `get_user_class_name()`, `is_valid_user_class()`,
 * `cur_user_check()` and `can_access_torrent()` helpers.
 */
final class User
{
    /**
     * @param  array<string, mixed>  $options
     */
    public static function getUserClassName(int|string $class, bool $compact = false, bool $b_colored = false, bool $I18N = false, array $options = []): string
    {
        return UserClass::name($class, $compact, $b_colored, $I18N, $options);
    }

    public static function isValidUserClass(mixed $class): bool
    {
        return Validators::isUserClass($class);
    }

    public static function currentUserCheck(): void
    {
        LegacyAuth::currentUserCheck(LegacyAuthContext::fromSupportContext());
    }

    /**
     * @param  array<array-key, mixed>|int|string  $torrent
     */
    public static function canAccessTorrent(array|int|string $torrent, int|string $uid): bool
    {
        return TorrentAccess::canAccess($torrent, $uid);
    }
}
