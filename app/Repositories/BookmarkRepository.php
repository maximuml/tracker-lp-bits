<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Database\QueryException;
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
        try {
            $result = $user->bookmarks()->create(['torrentid' => $torrentId]);
        } catch (QueryException $e) {
            // Lost a concurrent insert on the (userid, torrentid) unique key —
            // report the same already-bookmarked failure as the fast path.
            if ($e->getCode() === '23000') {
                throw new NexusException(Locale::trans('bookmark.torrent_already_bookmarked', ['torrent_id' => $torrentId], null));
            }
            throw $e;
        }

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
     * @return bool false when the (userid, torrentid) bookmark already exists —
     *              the unique key makes a concurrent duplicate insert lose
     */
    public function insertForUser(int $userId, int $torrentId): bool
    {
        return DB::table('bookmarks')->insertOrIgnore([
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]) === 1;
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
