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
    private function allUserTokenPermissions(): array
    {
        return array_map(
            static fn (RoutePermissionEnum $permission) => $permission->value,
            RoutePermissionEnum::cases()
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public function listUserTokenPermissions(bool $format = true): array
    {
        $permissions = $this->allUserTokenPermissions();
        if (! $format) {
            return $permissions;
        }

        return $this->formatPermissions($permissions);
    }

    /** @return  array<int|string, mixed> */
    public function listUserTokenPermissionAllowed(): array
    {
        return $this->formatPermissions(Setting::getPermissionUserTokenAllowed());
    }

    /**
     * @param  array<int|string, mixed>  $permissions
     * @return array<int|string, mixed>
     */
    private function formatPermissions(array $permissions): array
    {
        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission] = Locale::trans("route-permission.{$permission}.text", [], null);
        }

        return $result;
    }
}
