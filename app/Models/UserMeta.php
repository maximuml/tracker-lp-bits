<?php

/**
 * @property int $id
 * @property int $uid
 * @property string $meta_key
 * @property int $status
 * @property string|null $deadline
 * @property string|null $meta_value
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;

class UserMeta extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  list<string> */
    protected $fillable = ['uid', 'meta_key', 'meta_value', 'status', 'deadline'];

    /** @var  bool */
    public $timestamps = true;

    const STATUS_NORMAL = 0;

    const META_KEY_PERSONALIZED_USERNAME = 'PERSONALIZED_USERNAME';

    const META_KEY_CHANGE_USERNAME = 'CHANGE_USERNAME';

    /** @var  list<string> */
    protected $appends = ['meta_key_text'];

    /** @var  array<string, string> */
    protected $casts = [
        'deadline' => 'datetime',
    ];

    /** @var  array<int|string, mixed> */
    public static array $metaKeys = [
        self::META_KEY_PERSONALIZED_USERNAME => ['text' => 'PERSONALIZED_USERNAME', 'multiple' => false],
        self::META_KEY_CHANGE_USERNAME => ['text' => 'CHANGE_USERNAME', 'multiple' => false],
    ];

    /** @return  mixed */
    public static function listProps()
    {
        return [
            self::META_KEY_PERSONALIZED_USERNAME => nexus_trans('label.user_meta.meta_keys.' . self::META_KEY_PERSONALIZED_USERNAME),
            self::META_KEY_CHANGE_USERNAME => nexus_trans('label.user_meta.meta_keys.' . self::META_KEY_CHANGE_USERNAME),
        ];
    }

    /** @return  mixed */
    public function getMetaKeyTextAttribute()
    {
        return nexus_trans('label.user_meta.meta_keys.' . $this->meta_key) ?? '';
    }

    public function isValid(): bool
    {
        return $this->status == self::STATUS_NORMAL && ($this->getRawOriginal('deadline') === null || ($this->deadline && $this->deadline->gte(now())));
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

}
