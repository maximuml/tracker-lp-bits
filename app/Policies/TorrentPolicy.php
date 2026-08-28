<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Enums\TorrentApprovalStatus;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
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
        if ($torrent->banned === 'yes' && ! Permission::canViewBannedTorrent($user) && $torrent->owner != $user->id) {
            return false;
        }

        if (! TorrentAccess::canAccess($torrent->id, $user->id) && $torrent->owner != $user->id) {
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
        return $torrent->owner == $user->id || Permission::canManageTorrent($user);
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

        $approvalNotAllowed = $torrent->approval_status != TorrentApprovalStatus::ALLOW->value
            && ! SiteConfig::current()->torrent->approvalStatusNoneVisible();
        $allowOwnerDownload = $torrent->owner == $user->id;
        $canSeedBanned = Permission::canViewBannedTorrent($user);
        $canAccessTorrent = TorrentAccess::canAccess($torrent->id, $user->id);

        if ((($torrent->banned == 'yes' || ($approvalNotAllowed && ! $allowOwnerDownload)) && ! $canSeedBanned)
            || ! $canAccessTorrent
        ) {
            Logger::writeWithContext((string) sprintf('[DENY_DOWNLOAD], user: %s, approvalNotAllowed: %s, allowOwnerDownload: %s, canSeedBanned: %s, canAccessTorrent: %s', $user->id, $approvalNotAllowed ? 'true' : 'false', $allowOwnerDownload ? 'true' : 'false', $canSeedBanned ? 'true' : 'false', $canAccessTorrent ? 'true' : 'false'), (string) 'error', (bool) false);

            return false;
        }

        return true;
    }
}
