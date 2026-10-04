<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;

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

    /**
     * Bookmark row for a user on a torrent.
     */
    public function findByUserAndTorrent(int $userId, int $torrentId): ?\stdClass
    {
        /** @var \stdClass|null $row */
        $row = DB::table('bookmarks')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->first();

        return $row;
    }

    /**
     * @return int the new bookmark id
     */
    public function insertForUser(int $userId, int $torrentId): int
    {
        return (int) DB::table('bookmarks')->insertGetId([
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]);
    }

    public function deleteById(int $id): void
    {
        DB::table('bookmarks')->where('id', $id)->delete();
    }

    /**
     * @return array<int, int>
     */
    public function pluckTorrentIdsForUser(int $userId): array
    {
        /** @var array<int, int> */
        return Bookmark::query()->where('userid', $userId)->pluck('torrentid')->all();
    }
}
