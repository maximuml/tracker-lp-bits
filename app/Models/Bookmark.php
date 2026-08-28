<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends NexusModel
{
    /** @var string */
    protected $table = 'bookmarks';

    /** @var list<string> */
    protected $fillable = ['userid', 'torrentid'];

    /** @deprecated Use App\Enums\BookmarkFilter enum instead. */
    const FILTER_IGNORE = '0';

    /** @deprecated Use App\Enums\BookmarkFilter enum instead. */
    const FILTER_INCLUDE = '1';

    /** @deprecated Use App\Enums\BookmarkFilter enum instead. */
    const FILTER_EXCLUDE = '2';

    /** @return  BelongsTo<Torrent, $this> */
    public function torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
