<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $userid
 * @property string $subject
 * @property bool $locked
 * @property int $forumid
 * @property int $firstpost
 * @property int $lastpost
 * @property bool $sticky
 * @property int $hlcolor
 * @property int $views
 */

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var list<string> */
    protected $fillable = ['userid', 'subject', 'locked', 'forumid', 'firstpost', 'lastpost', 'sticky', 'hlcolor', 'views'];

    /** @var array<string, string> */
    protected $casts = [
        'locked' => 'boolean',
        'sticky' => 'boolean',
    ];

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    /** @return  BelongsTo<Forum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class, 'forumid');
    }

    /** @return  BelongsTo<Post, $this> */
    public function firstPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'firstpost');
    }

    /** @return  BelongsTo<Post, $this> */
    public function lastPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'lastpost');
    }
}
