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

class TorrentDenyReason extends NexusModel
{
    use NexusActivityLogTrait;

    /** @var string */
    protected $table = 'torrent_deny_reasons';

    /** @var bool */
    public $timestamps = true;

    /** @var list<string> */
    protected $fillable = ['name', 'hits', 'priority'];
}
