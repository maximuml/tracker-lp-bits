<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\Permission\RoutePermissionEnum;
use App\Models\PersonalAccessToken;
use App\Models\Setting;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Support\Carbon;

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

    /**
     * Delete user-issued Sanctum tokens not used in `$days` days —
     * `tokens:delete-expired` housekeeping.
     */
    public function deleteExpiredPersonalAccessTokens(?int $uid, int $days): int
    {
        $query = PersonalAccessToken::query()->where('tokenable_type', User::class);
        if ($uid !== null) {
            $query->where('tokenable_id', $uid);
        }

        return (int) $query->where('last_used_at', '<', Carbon::now()->subDays($days))->delete();
    }
}
