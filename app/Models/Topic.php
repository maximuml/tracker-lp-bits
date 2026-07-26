<?php

/**
 * @property int $id
 * @property int $userid
 * @property string $subject
 * @property string $locked
 * @property int $forumid
 * @property int $firstpost
 * @property int $lastpost
 * @property string $sticky
 * @property int $hlcolor
 * @property int $views
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class Topic extends NexusModel
{
    use NexusActivityLogTrait;

    protected $fillable = ['userid', 'subject', 'locked', 'forumid', 'firstpost', 'lastpost', 'sticky', 'hlcolor', 'views'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function forum(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Forum::class, 'forumid');
    }

    public function firstPost(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class, "firstpost");
    }

    public function lastPost(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class, "lastpost");
    }
}
