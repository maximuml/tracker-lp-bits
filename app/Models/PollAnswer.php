<?php

/**
 * @property int $id
 * @property int $pollid
 * @property int $userid
 * @property int $selection
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property int $counts
 * @property int $selection
 */
class PollAnswer extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var  string */
    protected $table = 'pollanswers';

    /** @var  list<string> */
    protected $fillable = ['pollid', 'userid', 'selection',];

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Poll, $this> */
    public function poll(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Poll::class, 'pollid');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
