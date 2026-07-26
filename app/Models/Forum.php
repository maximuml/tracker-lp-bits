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


class Forum extends NexusModel
{
    protected $fillable = ['sort', 'name', 'description', 'minclassread', 'minclasswrite', 'postcount', 'topiccount', 'minclasscreate', 'forid'];

    public function moderators(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, "forummods", "forumid", "userid");
    }
}
