<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;

class Permission
{
    public static function can(PermissionEnum $permission, ?User $user = null): bool
    {
        return self::userCan(self::user($user), $permission);
    }

    public static function assertCan(PermissionEnum $permission, ?User $user = null): void
    {
        if (! self::can($permission, $user)) {
            throw new InsufficientPermissionException;
        }
    }

    public static function canUploadToNormalSection(?User $user = null): bool
    {
        $user = self::user($user);

        return $user instanceof User && $user->uploadpos && self::userCan($user, PermissionEnum::UPLOAD);
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

    public static function canApproveTorrent(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_APPROVAL);
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

        return $user instanceof User && ($user->picker && self::canManageTorrent($user) || $user->class >= UserClassEnum::SYSOP->value);
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

    public static function canDeleteTorrent(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_DELETE);
    }

    public static function canSetTorrentOnPromotion(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_ON_PROMOTION);
    }

    public static function canMoveTorrent(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::MOVE_TORRENT);
    }

    public static function canViewAnonymous(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::VIEW_ANONYMOUS);
    }

    public static function canViewConfidentialLog(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::CONFIDENTIAL_LOG);
    }

    public static function canViewTorrentHistory(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_HISTORY);
    }

    public static function canViewTorrentStructure(?User $user = null): bool
    {
        return self::userCan($user, PermissionEnum::TORRENT_STRUCTURE);
    }

    private static function user(?User $user = null): ?User
    {
        if ($user instanceof User) {
            return $user;
        }

        $user = Auth::guard('nexus-web')->user();
        if ($user instanceof User) {
            return $user;
        }

        return Auth::user();
    }

    private static function userCan(?User $user, PermissionEnum $permission): bool
    {
        return Permissions::userCan($permission->value, false, $user instanceof User ? $user->id : 0);
    }
}
