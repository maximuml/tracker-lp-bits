<?php

/**
 * @property int $id
 * @property string $name
 * @property int $hits
 * @property int $priority
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Nexus\Database\NexusDB;

class TorrentDenyReason extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'torrent_deny_reasons';

    public $timestamps = true;

    protected $fillable = ['name', 'hits', 'priority',];

}
