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
    /** @var  string */
    protected $table = 'regimages';

    /** @var  list<string> */
    protected $fillable = ['imagehash', 'imagestring', 'dateline'];
}
