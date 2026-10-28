<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $uid
 * @property string $username
 * @property int $operator
 * @property string|null $reason
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

class UserBanLog extends NexusModel
{
    /** @var string */
    protected $table = 'user_ban_logs';

    /** @var list<string> */
    protected $fillable = ['uid', 'username', 'operator', 'reason'];

    /** @var bool */
    public $timestamps = true;
}
