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
    /** @var  list<string> */
    protected $fillable = ['torrent_id'];

    /** @var  bool */
    public $timestamps = true;
}
