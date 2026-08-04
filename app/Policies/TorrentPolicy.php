<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Support\TorrentAccess;

class TorrentPolicy extends BasePolicy
{
    public function before(User $user, $ability)
    {
        if ($ability === 'download') {
            return null;
        }
        return parent::before($user, $ability);
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Torrent $torrent): bool
    {
        if ($torrent->banned === 'yes' && !\user_can('seebanned', false, $user->id) && $torrent->owner != $user->id) {
            return false;
        }

        if (!\can_access_torrent($torrent->toArray(), $user->id) && $torrent->owner != $user->id) {
            return false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Torrent $torrent): bool
    {
        return $torrent->owner == $user->id || \user_can('torrentmanage', false, $user->id);
    }

    public function delete(User $user, Torrent $torrent): bool
    {
        return false;
    }

    public function restore(User $user, Torrent $torrent): bool
    {
        return false;
    }

    public function forceDelete(User $user, Torrent $torrent): bool
    {
        return false;
    }

    public function comment(User $user, Torrent $torrent): bool
    {
        return $user->parked !== 'yes';
    }

    public function download(User $user, Torrent $torrent): bool
    {
        if ($user->downloadpos === 'no') {
            return false;
        }

        $approvalNotAllowed = $torrent->approval_status != Torrent::APPROVAL_STATUS_ALLOW
            && Setting::get('torrent.approval_status_none_visible') == 'no';
        $allowOwnerDownload = $torrent->owner == $user->id;
        $canSeedBanned = user_can('seebanned', false, $user->id);
        $canAccessTorrent = TorrentAccess::canAccess($torrent->id, $user->id);

        if ((($torrent->banned == 'yes' || ($approvalNotAllowed && !$allowOwnerDownload)) && !$canSeedBanned)
            || !$canAccessTorrent
        ) {
            do_log(sprintf(
                "[DENY_DOWNLOAD], user: %s, approvalNotAllowed: %s, allowOwnerDownload: %s, canSeedBanned: %s, canAccessTorrent: %s",
                $user->id,
                $approvalNotAllowed ? 'true' : 'false',
                $allowOwnerDownload ? 'true' : 'false',
                $canSeedBanned ? 'true' : 'false',
                $canAccessTorrent ? 'true' : 'false'
            ), 'error');
            return false;
        }

        return true;
    }
}
