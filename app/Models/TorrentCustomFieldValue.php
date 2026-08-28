<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $torrent_id
 * @property int $custom_field_id
 * @property string|null $custom_field_value
 * @property string $created_at
 * @property string $updated_at
 */

namespace App\Models;

class TorrentCustomFieldValue extends NexusModel
{
    /** @var string */
    protected $table = 'torrents_custom_field_values';

    /** @var list<string> */
    protected $fillable = [
        'torrent_id', 'custom_field_id', 'custom_field_value',
    ];

    /** @var bool */
    public $timestamps = true;
}
