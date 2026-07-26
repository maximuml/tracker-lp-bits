<?php

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 */
namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Thank extends NexusModel
{
    protected $fillable = ['torrentid', 'userid'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    /**
     * @return BelongsTo<Torrent, $this>
     */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }
}
