<?php

/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class UserModifyLog extends NexusModel
{
    /** @var  list<string> */
    protected $fillable = ['user_id', 'content', ];

    /** @var  bool */
    public $timestamps = true;

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

}
