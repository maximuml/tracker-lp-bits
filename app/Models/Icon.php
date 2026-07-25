<?php

namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

/**
 * @property int $id
 * @property string $folder
 * @property string $multilang
 */
class Icon extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'caticons';

    protected $fillable = ['name', 'folder', 'cssfile', 'multilang', 'secondicon', 'designer', 'comment'];
}
