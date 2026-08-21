<?php

/**
 * @property int $id
 * @property int $topicid
 * @property int $userid
 * @property string|null $added
 * @property string|null $body
 * @property string|null $ori_body
 * @property int $editedby
 * @property string|null $editdate
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends NexusModel
{
    /** @var list<string> */
    protected $fillable = [
        'topicid', 'userid', 'added', 'body', 'ori_body', 'editedby', 'editdate',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'editdate' => 'datetime',
    ];

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
