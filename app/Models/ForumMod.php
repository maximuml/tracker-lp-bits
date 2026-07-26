<?php

/**
 * @property int $id
 * @property int $forumid
 * @property int $userid
 */
namespace App\Models;
class ForumMod extends NexusModel
{
    protected $table = 'forummods';

    protected $fillable = ['forumid', 'userid'];

}
