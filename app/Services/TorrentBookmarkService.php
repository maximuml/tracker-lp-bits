<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Globals;
use Illuminate\Support\Facades\DB;

/**
 * Handles torrent bookmark toggle and the legacy "thanks" page action.
 *
 * Bookmark toggle is a simple add/remove with cache invalidation.
 * The legacy thanks page adds a thanks record and grants bonus points
 * to both the thanker and the torrent owner.
 */
final class TorrentBookmarkService
{
    /**
     * Toggle a bookmark for a user on a torrent.
     *
     * @return string 'added', 'deleted', or 'failed' if torrent doesn't exist.
     */
    public function toggleBookmark(int $userId, int $torrentId): string
    {
        // Verify the torrent exists before adding a bookmark — prevents
        // orphaned bookmark records for non-existent torrents.
        $torrentExists = DB::table('torrents')->where('id', $torrentId)->exists();
        if (! $torrentExists) {
            return 'failed';
        }

        $bookmark = DB::table('bookmarks')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->first();

        if ($bookmark) {
            DB::table('bookmarks')->where('id', (int) $bookmark->id)->delete();
            $status = 'deleted';
        } else {
            DB::table('bookmarks')->insertGetId([
                'torrentid' => $torrentId,
                'userid' => $userId,
            ]);
            $status = 'added';
        }

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('user_'.$userId.'_bookmark_array');
        }

        return $status;
    }

    /**
     * Record a thanks on a torrent and grant bonus points.
     *
     * @param  array<string, mixed>|null  $currentUser
     * @return array{torrentid: int, owner: int}
     *
     * @throws \RuntimeException If the torrent does not exist or the user already thanked.
     */
    public function thankTorrent(?array $currentUser, int $torrentId): array
    {
        $userId = (int) ($currentUser['id'] ?? 0);

        $torrentOwner = (int) DB::table('torrents')->where('id', $torrentId)->value('owner');
        if ($torrentOwner === 0) {
            throw new \RuntimeException('Invalid torrent id!');
        }

        $existing = DB::table('thanks')
            ->where('torrentid', $torrentId)
            ->where('userid', $userId)
            ->count();
        if ($existing !== 0) {
            throw new \RuntimeException('You already said thanks!');
        }

        DB::table('thanks')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
        ]);

        $saythanksBonus = (float) app(Globals::class)->get('saythanks_bonus', 0);
        $receivethanksBonus = (float) app(Globals::class)->get('receivethanks_bonus', 0);
        Bonus::updatePoints('+', $saythanksBonus, $userId);
        Bonus::updatePoints('+', $receivethanksBonus, $torrentOwner);

        return ['torrentid' => $torrentId, 'owner' => $torrentOwner];
    }
}
