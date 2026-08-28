<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Permission\RoutePermissionEnum;
use App\Models\Setting;
use App\Support\Locale;

class TokenRepository extends BaseRepository
{
    /**
     * @return list<string>
     */
    private static function allUserTokenPermissions(): array
    {
        return array_map(
            static fn (RoutePermissionEnum $permission) => $permission->value,
            RoutePermissionEnum::cases()
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function listUserTokenPermissions(bool $format = true): array
    {
        $permissions = self::allUserTokenPermissions();
        if (! $format) {
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
     * @return array<int|string, mixed>
     */
    private static function formatPermissions(array $permissions): array
    {
        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission] = Locale::trans("route-permission.{$permission}.text", [], null);
        }

        return $result;
    }
}
