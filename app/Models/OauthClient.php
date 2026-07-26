<?php

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $secret
 * @property string|null $provider
 * @property string $redirect
 * @property int $personal_access_client
 * @property int $password_client
 * @property int $revoked
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property int $skips_authorization
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

class OauthClient extends Client
{
    use NexusActivityLogTrait;

    protected static function booted(): void
    {
        static::creating(function (OauthClient $model) {
            $model->secret = Str::random(40);
        });
    }
    public function skipsAuthorization(): bool
    {
        return (bool)$this->skips_authorization;
    }
}
