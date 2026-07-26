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


class Post extends NexusModel
{
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
