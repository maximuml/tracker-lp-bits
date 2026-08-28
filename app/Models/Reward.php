<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 * @property int $value
 * @property string $created_at
 * @property string $updated_at
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends NexusModel
{
    /** @var string */
    protected $table = 'magic';

    /** @var list<string> */
    protected $fillable = ['torrentid', 'userid', 'value'];

    /** @var bool */
    public $timestamps = true;

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
