<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $inviter
 * @property string $invitee
 * @property string $hash
 * @property string|null $time_invited
 * @property int $valid
 * @property int|null $invitee_register_uid
 * @property string|null $invitee_register_email
 * @property string|null $invitee_register_username
 * @property string|null $expired_at
 * @property string $created_at
 * @property string|null $pre_register_email
 * @property string|null $pre_register_username
 */

namespace App\Models;

use App\Enums\InviteValid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invite extends NexusModel
{
    /** @var string */
    protected $table = 'invites';

    const TEMPORARY_INVITE_VALID_DAYS = 7;

    /** @var array<string, string> */
    protected $casts = [
        'expired_at' => 'datetime',
    ];

    /** @var array<int|string, mixed> */
    public static $validInfo = [
        InviteValid::NO->value => ['text' => 'No'],
        InviteValid::YES->value => ['text' => 'Yes'],
    ];

    /** @var list<string> */
    protected $fillable = [
        'inviter', 'invitee', 'hash', 'time_invited', 'valid',
        'invitee_register_uid', 'invitee_register_email', 'invitee_register_username',
        'pre_register_email', 'pre_register_username',
    ];

    /** @return  mixed */
    public function getValidTextAttribute()
    {
        return self::$validInfo[$this->valid]['text'] ?? '';
    }

    /** @return  BelongsTo<User, $this> */
    public function inviter_user()
    {
        return $this->belongsTo(User::class, 'inviter');
    }

    /** @return  BelongsTo<User, $this> */
    public function invitee_user()
    {
        return $this->belongsTo(User::class, 'invitee_register_uid');
    }
}
