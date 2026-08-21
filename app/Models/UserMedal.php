<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $medal_id
 * @property string|null $expire_at
 * @property string|null $bonus_addition_expire_at
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $status
 * @property int $priority
 */

namespace App\Models;

use App\Support\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 */
class UserMedal extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['uid', 'medal_id', 'expire_at', 'status', 'bonus_addition_expire_at'];

    const STATUS_NOT_WEARING = 0;

    const STATUS_WEARING = 1;

    public function getWearingStatusTextAttribute(): string
    {
        return Locale::trans('medal.wearing_status_text.'.$this->status, [], null);
    }

    /** @return  array<int|string, mixed> */
    public static function listWearingStatusLabels(): array
    {
        return [
            self::STATUS_WEARING => Locale::trans('medal.wearing_status_text.'.self::STATUS_WEARING, [], null),
            self::STATUS_NOT_WEARING => Locale::trans('medal.wearing_status_text.'.self::STATUS_NOT_WEARING, [], null),
        ];
    }

    /** @return  BelongsTo<Medal, $this> */
    public function medal()
    {
        return $this->belongsTo(Medal::class, 'medal_id');
    }

    /** @return  BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }
}
