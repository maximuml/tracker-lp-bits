<?php

/**
 * @property int $id
 * @property string $imagehash
 * @property string $imagestring
 * @property int $dateline
 */
namespace App\Models;


use App\Models\Traits\NexusActivityLogTrait;

class RegImage extends NexusModel
{
    protected $table = 'regimages';

    protected $fillable = ['imagehash', 'imagestring', 'dateline'];
}
