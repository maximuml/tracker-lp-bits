<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $forumid
 * @property int $userid
 */

namespace App\Models;

class ForumMod extends NexusModel
{
    /** @var string */
    protected $table = 'forummods';

    /** @var list<string> */
    protected $fillable = ['forumid', 'userid'];
}
