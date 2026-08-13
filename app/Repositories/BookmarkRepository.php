<?php

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;

class BookmarkRepository extends BaseRepository
{
    /**
     * @param  \App\Models\User  $user
     * @param  mixed  $torrentId
     * @return  mixed
     */
    public function add(User $user, $torrentId)
    {
        $torrent = Torrent::query()->find($torrentId);
        if (!$torrent) {
            throw new NexusException(\App\Support\Locale::trans('bookmark.torrent_not_exists', ['torrent_id' => $torrentId], null));
        }
        $torrent->checkIsNormal();
        $exists = $user->bookmarks()->where('torrentid', $torrentId)->exists();
        if ($exists) {
            throw new NexusException(\App\Support\Locale::trans('bookmark.torrent_already_bookmarked', ['torrent_id' => $torrentId], null));
        }
        $result = $user->bookmarks()->create(['torrentid' => $torrentId]);
        return $result;
    }

    /**
     * @param  \App\Models\User  $user
     * @param  mixed  $torrentId
     * @return  mixed
     */
    public function remove(User $user, $torrentId)
    {
        /**
         * @var Bookmark $record
         */
        $record = $user->bookmarks()->where('torrentid', $torrentId)->first();
        if (!$record) {
            throw new NexusException(\App\Support\Locale::trans('bookmark.torrent_has_not_been_bookmarked', ['torrent_id' => $torrentId], null));
        }
        \App\Support\Logger::writeWithContext((string) "going to remove bookmark of torrent: {$torrentId}", (string) 'info', (bool) false);
        $record->delete();
        return true;
    }
}
