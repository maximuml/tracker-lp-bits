<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Query scopes for the Torrent model.
 */
trait HasTorrentScopes
{
    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeWhereInfoHash($query, string $binaryHash)
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            return $query->whereRaw(
                "info_hash = decode(?, 'hex')",
                [bin2hex($binaryHash)]
            );
        } elseif (DB::connection()->getDriverName() === 'mysql') {
            return $query->where('info_hash', $binaryHash);
        }
        throw new \RuntimeException('Not supported database');
    }

    /**
     * @param  mixed  $query
     * @param  mixed  $visible
     * @return mixed
     */
    public function scopeVisible($query, $visible = self::VISIBLE_YES)
    {
        $query->where('visible', $visible);
    }

    /**
     * @param  mixed  $query
     * @return mixed
     */
    public function scopeNormal($query)
    {
        $query->where('visible', self::VISIBLE_YES)->where('banned', self::BANNED_NO);
    }
}
