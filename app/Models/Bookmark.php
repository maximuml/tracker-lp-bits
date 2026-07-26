<?php

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 */
namespace App\Models;


class Bookmark extends NexusModel
{
    protected $table = 'bookmarks';

    protected $fillable = ['userid', 'torrentid'];

    const FILTER_IGNORE = '0';
    const FILTER_INCLUDE = '1';
    const FILTER_EXCLUDE = '2';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Torrent, $this>
     */
    public function torrent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
