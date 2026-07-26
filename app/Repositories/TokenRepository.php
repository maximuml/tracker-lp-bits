<?php
namespace App\Repositories;

use App\Enums\Permission\RoutePermissionEnum;
use App\Models\Setting;

class TokenRepository extends BaseRepository
{
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $userTokenPermissions = [
        RoutePermissionEnum::TORRENT_LIST->value,
        RoutePermissionEnum::TORRENT_VIEW->value,
        RoutePermissionEnum::TORRENT_UPLOAD->value,
        RoutePermissionEnum::USER_VIEW->value,
        RoutePermissionEnum::BOOKMARK_STORE->value,
        RoutePermissionEnum::BOOKMARK_DELETE->value,
    ];

    /**
     * @param  bool  $format
     * @return  array<int|string, mixed>
     */
    public static function listUserTokenPermissions(bool $format = true): array
    {
        if (!$format) {
            return self::$userTokenPermissions;
        }
        return self::formatPermissions(self::$userTokenPermissions);
    }

    /** @return  array<int|string, mixed> */
    public static function listUserTokenPermissionAllowed(): array
    {
        return self::formatPermissions(Setting::getPermissionUserTokenAllowed());
    }

    /**
     * @param  array<int|string, mixed>  $permissions
     * @return  array<int|string, mixed>
     */
    private static function formatPermissions(array $permissions): array
    {
        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission] = nexus_trans("route-permission.{$permission}.text");
        }
        return $result;
    }
}
