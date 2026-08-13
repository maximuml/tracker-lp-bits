<?php
namespace App\Repositories;

use App\Enums\Permission\RoutePermissionEnum;
use App\Models\Setting;

class TokenRepository extends BaseRepository
{
    /**
     * @return  list<string>
     */
    private static function allUserTokenPermissions(): array
    {
        return array_map(
            static fn (RoutePermissionEnum $permission) => $permission->value,
            RoutePermissionEnum::cases()
        );
    }

    /**
     * @param  bool  $format
     * @return  array<int|string, mixed>
     */
    public static function listUserTokenPermissions(bool $format = true): array
    {
        $permissions = self::allUserTokenPermissions();
        if (!$format) {
            return $permissions;
        }
        return self::formatPermissions($permissions);
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
