<?php

/**
 * @property int $id
 * @property int $torrent_id
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequireSeedTorrent extends NexusModel
{
    protected $fillable = ['torrent_id'];

    public $timestamps = true;
}
