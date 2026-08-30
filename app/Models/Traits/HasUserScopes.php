<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query scopes for the User model.
 */
trait HasUserScopes
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeNormal(Builder $query): Builder
    {
        return $query->where('status', UserStatus::CONFIRMED->value)->where('enabled', true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDonating(Builder $query): Builder
    {
        return $query->where('donor', true)->where(function (Builder $query) {
            return $query->whereNull('donoruntil')
                ->orWhere('donoruntil', '>=', now());
        });
    }
}
