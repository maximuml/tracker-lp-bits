<?php

/**
 * @property int $id
 * @property int $user_id
 * @property int $provider_id
 * @property string $provider_user_id
 * @property string $provider_email
 * @property string $provider_username
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class SocialAccount extends NexusModel
{
    /** @var  list<string> */
    protected $fillable = [
        'user_id', 'provider_id', 'provider_user_id', 'provider_username', 'provider_email',
    ];

    /** @var  bool */
    public $timestamps = true;

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
