<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Models\Torrent;
use App\Models\User;

/**
 * W1-06: Authorization policy for torrent mutations.
 * Extracts ownership/management checks that were previously inline in
 * TorrentEditRepository and TorrentUploadController.
 */
class TorrentPolicy extends BasePolicy
{
    /**
     * Whether the user can edit a torrent.
     * The owner and staff with TORRENT_MANAGE can edit.
     */
    public function update(User $user, Torrent $torrent): bool
    {
        if ($user->id === (int) $torrent->owner) {
            return true;
        }

        return Permission::canManageTorrent($user);
    }

    /**
     * Whether the user can move a torrent to a different category mode.
     */
    public function move(User $user): bool
    {
        return Permission::canMoveTorrent($user);
    }

    /**
     * Whether the user can manage torrent visibility/promotion/pos state.
     */
    public function manage(User $user): bool
    {
        return Permission::canManageTorrent($user);
    }

    /**
     * Whether the user can set torrent promotion state.
     */
    public function setPromotion(User $user): bool
    {
        return Permission::canSetTorrentOnPromotion($user);
    }

    /**
     * Whether the user can set torrent pos state (sticky/position).
     */
    public function setPosState(User $user): bool
    {
        return Permission::canSetTorrentPosState($user);
    }

    /**
     * Whether the user can set torrent hit-and-run.
     */
    public function setHitAndRun(User $user): bool
    {
        return Permission::canSetTorrentHitAndRun($user);
    }

    /**
     * Whether the user can set torrent price.
     */
    public function setPrice(User $user): bool
    {
        return Permission::canSetTorrentPrice($user);
    }

    /**
     * Whether the user can upload torrents.
     */
    public function upload(User $user): bool
    {
        return (bool) $user->uploadpos;
    }
}
