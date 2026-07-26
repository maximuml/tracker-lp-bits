<?php

/**
 * @property int $id
 * @property int $torrent_id
 * @property int $custom_field_id
 * @property string|null $custom_field_value
 * @property string $created_at
 * @property string $updated_at
 */
namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentCustomFieldValue extends NexusModel
{
    protected $table = 'torrents_custom_field_values';

    protected $fillable = [
        'torrent_id', 'custom_field_id', 'custom_field_value',
    ];

    public $timestamps = true;

}
