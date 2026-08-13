<?php

/**
 * @property int $id
 * @property string $name
 * @property int $get_type
 * @property string|null $description
 * @property string|null $image_large
 * @property string|null $image_small
 * @property int $price
 * @property int $display_on_medal_page
 * @property int|null $duration
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $sale_begin_time
 * @property string|null $sale_end_time
 * @property int|null $inventory
 * @property float $bonus_addition_factor
 * @property int $bonus_addition_duration
 * @property float $gift_fee_factor
 * @property int $priority
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Carbon\Carbon;

/**
 * @property int|null $inventory
 * @property int|null $duration
 * @property-read UserMedal $pivot
 */
class Medal extends NexusModel
{
    use NexusActivityLogTrait;

    const GET_TYPE_EXCHANGE = 1;

    const GET_TYPE_GRANT = 2;

    /** @var  array<int|string, mixed> */
    public static array $getTypeText = [
        self::GET_TYPE_EXCHANGE => ['text' => 'Exchange'],
        self::GET_TYPE_GRANT => ['text' => 'Grant'],
    ];

    /** @var  list<string> */
    protected $fillable = [
        'name', 'description', 'image_large', 'image_small', 'price', 'duration', 'get_type',
        'display_on_medal_page', 'sale_begin_time', 'sale_end_time', 'inventory', 'bonus_addition_factor',
        'gift_fee_factor', 'priority', 'bonus_addition_duration'
    ];

    /** @var  bool */
    public $timestamps = true;

    /** @var  array<string, string> */
    protected $casts = [
        'sale_begin_time' => 'datetime',
        'sale_end_time' => 'datetime',
    ];

    /**
     * @param  mixed  $onlyKeyValues
     * @return  array<int|string, mixed>
     */
    public static function listGetTypes($onlyKeyValues = false): array
    {
        $results = self::$getTypeText;
        $keyValues = [];
        foreach ($results as $type => &$info) {
            $text = \App\Support\Locale::trans("medal.get_types.{$type}", [], null);
            $keyValues[$type] = $text;
            $info['text'] = $text;
        }
        if ($onlyKeyValues) {
            return $keyValues;
        }
        return $results;
    }

    /** @param  mixed  $value */
    public function getGetTypeTextAttribute($value): string
    {
        return \App\Support\Locale::trans("medal.get_types." . $this->get_type, [], null);
    }

    public function getInventoryTextAttribute(): string
    {
        return $this->inventory !== null ? (string) $this->inventory : \App\Support\Locale::trans("label.infinite", [], null);
    }

    /** @param  mixed  $value */
    public function getDurationTextAttribute($value): string
    {
        if ($this->duration > 0) {
            return (string) $this->duration;
        }
        return \App\Support\Locale::trans("label.permanent", [], null);
    }

    /** @return  mixed */
    public function checkCanBeBuy()
    {
        if ($this->get_type == self::GET_TYPE_GRANT) {
            throw new \RuntimeException(\App\Support\Locale::trans('medal.grant_only', [], null));
        }
        $now = now();
        if ($this->sale_begin_time && $this->sale_begin_time->gt($now)) {
            throw new \RuntimeException(\App\Support\Locale::trans('medal.before_sale_begin_time', [], null));
        }
        if ($this->sale_end_time && $this->sale_end_time->lt($now)) {
            throw new \RuntimeException(\App\Support\Locale::trans('medal.after_sale_end_time', [], null));
        }
        if ($this->inventory !== null && $this->inventory <= 0) {
            throw new \RuntimeException(\App\Support\Locale::trans('medal.inventory_empty', [], null));
        }
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this> */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_medals', 'medal_id', 'uid')->withTimestamps();
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this> */
    public function valid_users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->users()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

}
