<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\TorrentVisible;

/**
 * Query scopes for the Torrent model.
 */
trait HasTorrentScopes
{
    /**
     * @param  mixed  $query
     * @param  mixed  $visible
     * @return mixed
     */
    public function scopeVisible($query, $visible = TorrentVisible::YES->value)
    {
        $query->where('visible', $visible);
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeNormal($query)
    {
        $query->where('visible', TorrentVisible::YES->value)->where('banned', self::BANNED_NO);
    }
}
