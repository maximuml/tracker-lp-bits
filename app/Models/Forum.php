<?php

declare(strict_types=1);

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

    /** @var array<string, string> */
    protected $casts = [
        'sort' => 'integer',
        'minclassread' => 'integer',
        'minclasswrite' => 'integer',
        'minclasscreate' => 'integer',
        'postcount' => 'integer',
        'topiccount' => 'integer',
        'forid' => 'integer',
    ];

    /** @return BelongsToMany<User, $this> */
    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forummods', 'forumid', 'userid');
    }

    /** @return BelongsTo<OverForum, $this> */
    public function overForum()
    {
        return $this->belongsTo(OverForum::class, 'forid');
    }
}
