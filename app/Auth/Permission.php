<?php

namespace App\Auth;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;

class Permission
{
    public static function canUploadToSpecialSection(?User $user = null): bool
    {
        return self::canUploadToNormalSection($user) && self::userCan($user, PermissionEnum::UPLOAD_TO_SPECIAL_SECTION);
    }

    public static function canUploadToNormalSection(?User $user = null): bool
    {
        $user = self::user($user);
        return $user->uploadpos == 'yes' && self::userCan($user, PermissionEnum::UPLOAD);
    }

    public static function canViewSpecialSection(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_VIEW_SPECIAL);
    }

    public static function canBeAnonymous(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::BE_ANONYMOUS);
    }

    public static function canSetTorrentHitAndRun(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_SET_HR);
    }

    public static function canSetTorrentPrice(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_SET_PRICE);
    }

    public static function canSetTorrentPosState(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_SET_STICKY);
    }

    public static function canTorrentApprovalAllowAutomatic(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_APPROVAL_ALLOW_AUTOMATIC);
    }

    public static function canManageTorrent(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_MANAGE);
    }

    public static function canPickTorrent(?User $user = null): bool
    {
        $user = self::user($user);
        return $user->picker == 'yes' && self::canManageTorrent($user) || $user->class >= User::CLASS_SYSOP;
    }

    public static function canSetTorrentSpecialTag(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_SET_SPECIAL_TAG);
    }

    public static function canManageUserBasicInfo(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::MANAGE_USER_BASIC_INFO);
    }

    public static function canManageUserConfidentialInfo(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO);
    }

    public static function canViewUserConfidentialInfo(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO);
    }

    public static function canViewBannedTorrent(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_VIEW_BANNED);
    }

    private static function user(?User $user = null): User
    {
        return $user ?? Auth::user();
    }

    private static function userCan(?User $user, PermissionEnum $permission): bool
    {
        return Permissions::userCan($permission->value, false, $user?->id ?? 0);
    }
}
