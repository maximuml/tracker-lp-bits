<?php

/**
 * @property int $id
 * @property int $torrent
 * @property string $filename
 * @property int $size
 */

namespace App\Models;

class File extends NexusModel
{
    /** @var string */
    protected $table = 'files';

    /** @var list<string> */
    protected $fillable = ['torrent', 'filename', 'size'];
}
