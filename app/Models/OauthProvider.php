<?php

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $client_id
 * @property string $client_secret
 * @property string $authorization_endpoint_url
 * @property string $token_endpoint_url
 * @property string $user_info_endpoint_url
 * @property string $id_claim
 * @property string|null $username_claim
 * @property string|null $email_claim
 * @property string|null $level_claim
 * @property string|null $level_limit
 * @property int $enabled
 * @property int $priority
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use App\Support\Url;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;
use Ramsey\Uuid;

class OauthProvider extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = [
        'uuid', 'name', 'client_id', 'client_secret', 'authorization_endpoint_url', 'token_endpoint_url',
        'user_info_endpoint_url', 'id_claim', 'username_claim', 'email_claim', 'enabled', 'priority',
        'level_claim', 'level_limit',
    ];

    /** @var bool */
    public $timestamps = true;

    const NEW_UUID_CACHE_KEY = 'new_oauth_provider_uuid';

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (OauthProvider $model) {
            $model->uuid = self::getNewUuid();
        });
        static::created(function (OauthProvider $model) {
            NexusDB::cache_del(self::NEW_UUID_CACHE_KEY);
        });
    }

    public static function getCallbackUrl(string $uuid): string
    {
        return sprintf('%s/oauth/callback/%s', Url::schemeAndHost(false), $uuid);
    }

    private static function getNewUuid(): string
    {
        return Cache::remember(self::NEW_UUID_CACHE_KEY, 86400 * 365, function () {
            return UUid\v4();
        });
    }
}
