<?php

/**
 * @property int $id
 * @property int $pollid
 * @property int $userid
 * @property int $selection
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $counts
 * @property int $selection
 */
class PollAnswer extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'pollanswers';

    /** @var list<string> */
    protected $fillable = ['pollid', 'userid', 'selection'];

    /** @return  BelongsTo<Poll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'pollid');
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
