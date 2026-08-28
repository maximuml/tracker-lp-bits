<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property string|null $created_at
 * @property string|null $updated_at
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModifyLog extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'content'];

    /** @var bool */
    public $timestamps = true;

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
