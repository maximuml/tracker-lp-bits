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

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends NexusModel
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'provider_id', 'provider_user_id', 'provider_username', 'provider_email',
    ];

    /** @var bool */
    public $timestamps = true;

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
