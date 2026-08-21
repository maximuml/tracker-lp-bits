<?php

/**
 * @property int $id
 * @property int $sort
 * @property string $name
 * @property string $description
 * @property int $minclassread
 * @property int $minclasswrite
 * @property int $postcount
 * @property int $topiccount
 * @property int $minclasscreate
 * @property int $forid
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Forum extends NexusModel
{
    /** @var list<string> */
    protected $fillable = ['sort', 'name', 'description', 'minclassread', 'minclasswrite', 'postcount', 'topiccount', 'minclasscreate', 'forid'];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this> */
    public function moderators(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forummods', 'forumid', 'userid');
    }

    /** @return BelongsTo<OverForum, $this> */
    public function overForum()
    {
        return $this->belongsTo(OverForum::class, 'forid');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<OverForum, $this> */
    public function overForum()
    {
        return $this->belongsTo(OverForum::class, 'forid');
    }
}
