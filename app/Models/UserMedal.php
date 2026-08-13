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

/**
 * @property int $id
 */
class UserMedal extends NexusModel
{
    /** @var  list<string> */
    protected $fillable = ['uid', 'medal_id', 'expire_at', 'status', 'bonus_addition_expire_at'];

    const STATUS_NOT_WEARING = 0;
    const STATUS_WEARING = 1;

    public function getWearingStatusTextAttribute(): string
    {
        return \App\Support\Locale::trans("medal.wearing_status_text." . $this->status, [], null);
    }

    /** @return  array<int|string, mixed> */
    public static function listWearingStatusLabels(): array
    {
        return [
            self::STATUS_WEARING => \App\Support\Locale::trans("medal.wearing_status_text." . self::STATUS_WEARING, [], null),
            self::STATUS_NOT_WEARING => \App\Support\Locale::trans("medal.wearing_status_text." . self::STATUS_NOT_WEARING, [], null),
        ];
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Medal, $this> */
    public function medal()
    {
        return $this->belongsTo(Medal::class, 'medal_id');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

}
