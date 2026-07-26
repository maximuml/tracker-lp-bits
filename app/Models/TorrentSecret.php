<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $torrent_id
 * @property string $secret
 * @property string $created_at
 * @property string $updated_at
 */
namespace App\Models;

class TorrentSecret extends NexusModel
{
    protected $fillable = ['uid', 'torrent_id', 'secret'];
}
