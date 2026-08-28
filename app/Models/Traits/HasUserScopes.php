<?php

declare(strict_types=1);

namespace App\Models\Traits;

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
        return $query->where('status', self::STATUS_CONFIRMED)->where('enabled', self::ENABLED_YES);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDonating(Builder $query): Builder
    {
        return $query->where('donor', 'yes')->where(function (Builder $query) {
            return $query->whereNull('donoruntil')
                ->orWhere('donoruntil', '>=', now());
        });
    }
}
