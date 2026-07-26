<?php

namespace App\Models;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $aaguid
 * @property string $credential_id
 * @property string $public_key
 * @property int|null $counter
 * @property-read User $user
 */
class Passkey extends NexusModel
{
    protected $table = 'user_passkeys';

    public $timestamps = true;

    protected $fillable = [
        'id', 'user_id', 'aaguid', 'credential_id', 'public_key', 'counter',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getAaguidFormatted(): string
    {
        $guid = $this->aaguid;
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($guid, 0, 8),
            substr($guid, 8, 4),
            substr($guid, 12, 4),
            substr($guid, 16, 4),
            substr($guid, 20, 12)
        );
    }

}
