<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Locale;
use App\Support\Logger;

class BookmarkRepository extends BaseRepository
{
    /**
     * @param  mixed  $torrentId
     * @return mixed
     */
    public function add(User $user, $torrentId)
    {
        $torrent = Torrent::query()->find((int) $torrentId);
        if (! $torrent) {
            throw new NexusException(Locale::trans('bookmark.torrent_not_exists', ['torrent_id' => $torrentId], null));
        }
        $torrent->checkIsNormal();
        $exists = $user->bookmarks()->where('torrentid', $torrentId)->exists();
        if ($exists) {
            throw new NexusException(Locale::trans('bookmark.torrent_already_bookmarked', ['torrent_id' => $torrentId], null));
        }
        $result = $user->bookmarks()->create(['torrentid' => $torrentId]);

        return $result;
    }

    /**
     * @param  mixed  $torrentId
     * @return mixed
     */
    public function remove(User $user, $torrentId)
    {
        /**
         * @var Bookmark $record
         */
        $record = $user->bookmarks()->where('torrentid', $torrentId)->first();
        if (! $record) {
            throw new NexusException(Locale::trans('bookmark.torrent_has_not_been_bookmarked', ['torrent_id' => $torrentId], null));
        }
        Logger::writeWithContext((string) "going to remove bookmark of torrent: {$torrentId}", (string) 'info', (bool) false);
        $record->delete();

        return true;
    }
}
