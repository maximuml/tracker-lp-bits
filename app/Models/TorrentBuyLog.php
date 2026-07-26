<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $torrent_id
 * @property int $price
 * @property string $channel
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

use Nexus\Database\NexusDB;

class TorrentBuyLog extends NexusModel
{
    /** @var  bool */
    public $timestamps = true;

    /** @var  list<string> */
    protected $fillable = ['uid', 'torrent_id', 'price', 'channel'];

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<Torrent, $this> */
    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

}
