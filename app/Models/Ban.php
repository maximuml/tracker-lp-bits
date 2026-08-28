<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $added
 * @property int $addedby
 * @property string $comment
 * @property int $first
 * @property int $last
 */
class Ban extends Model
{
    protected $table = 'bans';

    public $timestamps = false;

    protected $fillable = ['added', 'addedby', 'comment', 'first', 'last'];

    /**
     * Convert a dotted-quad IP to its long-integer representation.
     */
    public static function ipToLong(string $ip): int|false
    {
        $long = ip2long($ip);

        return $long === false ? false : (int) $long;
    }

    /**
     * Convert a long-integer IP back to dotted-quad.
     */
    public static function longToIp(int $long): string
    {
        return long2ip($long) ?: '';
    }

    /** @return BelongsTo<User, $this> */
    public function addedByUser()
    {
        return $this->belongsTo(User::class, 'addedby');
    }
}
