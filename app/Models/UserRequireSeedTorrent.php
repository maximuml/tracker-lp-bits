<?php

/**
 * @property int $id
 * @property int $user_id
 * @property int $torrent_id
 * @property int $seed_time_begin
 * @property int $uploaded_begin
 * @property string|null $last_settlement_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class UserRequireSeedTorrent extends NexusModel
{
    protected $fillable = ['user_id', 'torrent_id', 'seed_time_begin', 'uploaded_begin', 'last_settlement_at'];

    public $timestamps = true;
}
