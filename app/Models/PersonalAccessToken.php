<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property string|null $abilities
 * @property string|null $last_used_at
 * @property string|null $expires_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use App\Support\Locale;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function getAbilitiesTextAttribute(): string
    {
        $abilities = $this->abilities ?? [];
        if (in_array('*', $abilities)) {
            return 'ALL';
        }
        $result = [];
        foreach ($abilities as $ability) {
            if ($ability != '*') {
                $result[] = Locale::trans("route-permission.{$ability}.text", [], null);
            }
        }

        return implode(', ', $result);
    }
}
