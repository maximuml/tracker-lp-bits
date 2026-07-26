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

use Laravel\Passport\Client;

class SocialAccount extends NexusModel
{
    protected $fillable = [
        'user_id', 'provider_id', 'provider_user_id', 'provider_username', 'provider_email',
    ];

    public $timestamps = true;

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

}
