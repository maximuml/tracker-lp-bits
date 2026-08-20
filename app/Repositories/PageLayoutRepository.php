<?php

namespace App\Repositories;

use App\Models\Invite;
use App\Support\Hooks;
use App\Support\Menu;
use App\Support\SupportContext;
use Nexus\Database\NexusDB;
use Nexus\Nexus;

class PageLayoutRepository extends BaseRepository
{
    public function getInboxCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('receiver', $userId)
            ->where('location', '<>', 0)
            ->count();
    }

    public function getOutboxCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('sender', $userId)
            ->where('saved', 'yes')
            ->count();
    }

    public function getConnectable(int $userId): string
    {
        return NexusDB::table('peers')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->value('connectable') ?? 'unknown';
    }

    public function getActiveSeedCount(int $userId): int
    {
        return (int) NexusDB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 'yes')
            ->count();
    }

    public function getActiveLeechCount(int $userId): int
    {
        return (int) NexusDB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 'no')
            ->count();
    }

    public function getUnreadMessageCount(int $userId): int
    {
        return (int) NexusDB::table('messages')
            ->where('receiver', $userId)
            ->where('unread', 'yes')
            ->count();
    }

    public function getUnreadNewsCount(?string $lastHome): int
    {
        $query = NexusDB::table('news')->where('notify', 'yes');
        if (! empty($lastHome) && $lastHome !== '0000-00-00 00:00:00') {
            $query->where('added', '>', $lastHome);
        }

        return (int) $query->count();
    }

    public function getTotalReports(): int
    {
        return (int) NexusDB::table('reports')->count();
    }

    public function getTotalCheaters(): int
    {
        return (int) NexusDB::table('cheaters')->count();
    }

    public function getTorrentApprovalNoneCount(): int
    {
        return (int) NexusDB::table('torrents')->where('approval_status', 0)->count();
    }

    public function getSeedBoxApprovalCount(): int
    {
        return (int) NexusDB::table('seed_box_records')->where('status', 0)->count();
    }

    public function getOpenComplaintsCount(): int
    {
        return (int) NexusDB::table('complains')->where('answered', 0)->count();
    }

    public function getOpenReportsCount(): int
    {
        return (int) NexusDB::table('reports')->where('dealtwith', 0)->count();
    }

    public function getOpenCheatersCount(): int
    {
        return (int) NexusDB::table('cheaters')->where('dealtwith', 0)->count();
    }

    public function getPendingInviteCount(int $userId): int
    {
        return (int) Invite::query()
            ->where('inviter', $userId)
            ->where('invitee', '')
            ->where('expired_at', '>', now())
            ->count();
    }

    /** @param  array<string, mixed>  $data */
    public function updateUser(int $userId, array $data): void
    {
        NexusDB::table('users')->where('id', $userId)->update($data);
    }

    /**
     * Record per-request user access data and precompute the main menu so
     * the header view helper does not have to mutate state or touch Redis.
     */
    public function prepareAccess(): void
    {
        $user = SupportContext::getUser();
        if ($user === null || empty($user['id'])) {
            return;
        }

        $script = Nexus::instance()->getScript();
        if (in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            return;
        }

        SupportContext::addUserUpdate('last_access', date('Y-m-d H:i:s'));
        SupportContext::addUserUpdate('ip', $user['ip'] ?? SupportContext::getServerValue('REMOTE_ADDR', ''));

        IpLogRepository::saveToCache((int) $user['id']);

        $menuResult = Menu::render(
            $script,
            SupportContext::getLangFunctions(),
            (string) SupportContext::getGlobal('enableoffer', ''),
            (string) Hooks::applyFilter('nexus_menu') ?: null,
            $user,
            SupportContext::getCache(),
            (string) SupportContext::getGlobal('CURLANGDIR', ''),
        );

        SupportContext::setGlobal('nexus_menu_html', $menuResult['html']);
        SupportContext::setGlobal('nexus_menu_selected', $menuResult['selected']);

        if ((string) SupportContext::getGlobal('where_tweak', '') === 'yes') {
            SupportContext::addUserUpdate('page', $menuResult['selected']);
        }
    }

    /**
     * Flush any pending user updates to the database at the end of the request.
     */
    public function flushAccess(): void
    {
        $user = SupportContext::getUser();
        if ($user === null || empty($user['id'])) {
            return;
        }

        $userUpdateSet = SupportContext::getUserUpdateSet();
        if (empty($userUpdateSet)) {
            return;
        }

        $this->updateUser((int) $user['id'], $userUpdateSet);
    }
}
