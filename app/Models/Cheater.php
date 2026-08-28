<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string|null $added
 * @property int $userid
 * @property int $torrentid
 * @property int $uploaded
 * @property int $downloaded
 * @property int $anctime
 * @property int $seeders
 * @property int $leechers
 * @property int $hit
 * @property int $dealtby
 * @property int $dealtwith
 * @property string $comment
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheater extends NexusModel
{
    /** @var list<string> */
    protected $fillable = [
        'added', 'userid', 'torrentid', 'uploaded', 'downloaded', 'anctime', 'seeders', 'leechers', 'hit',
        'dealtby', 'dealtwith', 'comment',
    ];

    /** @return BelongsTo<Torrent, $this> */
    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    /** @return BelongsTo<User, $this> */
    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
