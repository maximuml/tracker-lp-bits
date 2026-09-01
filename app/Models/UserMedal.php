<?php

declare(strict_types=1);

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

use App\Enums\UserMedalStatus;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 */
class UserMedal extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['uid', 'medal_id', 'expire_at', 'status', 'bonus_addition_expire_at'];

    /** @var array<string, string> */
    protected $casts = [
        'expire_at' => 'datetime',
        'bonus_addition_expire_at' => 'datetime',
    ];

    public function getWearingStatusTextAttribute(): string
    {
        return Locale::trans('medal.wearing_status_text.'.$this->status, [], null);
    }

    /** @return  array<int|string, mixed> */
    public static function listWearingStatusLabels(): array
    {
        return [
            UserMedalStatus::WEARING->value => Locale::trans('medal.wearing_status_text.'.UserMedalStatus::WEARING->value, [], null),
            UserMedalStatus::NOT_WEARING->value => Locale::trans('medal.wearing_status_text.'.UserMedalStatus::NOT_WEARING->value, [], null),
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
