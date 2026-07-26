<?php

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

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function getAbilitiesTextAttribute(): string
    {
        if (in_array('*', $this->abilities)) {
            return 'ALL';
        }
        $result = [];
        foreach ($this->abilities as $ability) {
            if ($ability != '*') {
                $result[] = nexus_trans("route-permission.{$ability}.text");
            }
        }
        return implode(', ', $result);
    }
}
