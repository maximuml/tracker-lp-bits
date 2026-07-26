<?php

/**
 * @property int $id
 * @property int $torrent_id
 * @property int $tag_id
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class TorrentTag extends NexusModel
{
    public $timestamps = true;

    protected $fillable = [
        'torrent_id', 'tag_id', 'priority'
    ];


}
