<?php

namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property int $counts
 * @property int $selection
 */
class PollAnswer extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'pollanswers';

    protected $fillable = ['pollid', 'userid', 'selection',];

    public function poll(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Poll::class, 'pollid');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
