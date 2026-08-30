<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Invite;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\Language;
use App\Support\Menu;
use App\Support\RequestContext;
use App\Support\UserUpdateBatch;
use Illuminate\Support\Facades\DB;

class PageLayoutRepository extends BaseRepository
{
    public function getInboxCount(int $userId): int
    {
        return (int) DB::table('messages')
            ->where('receiver', $userId)
            ->where('location', '<>', 0)
            ->count();
    }

    public function getOutboxCount(int $userId): int
    {
        return (int) DB::table('messages')
            ->where('sender', $userId)
            ->where('saved', true)
            ->count();
    }

    public function getConnectable(int $userId): ?int
    {
        $value = DB::table('peers')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->value('connectable');

        return $value === null ? null : (int) $value;
    }

    public function getActiveSeedCount(int $userId): int
    {
        return (int) DB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 1)
            ->count();
    }

    public function getActiveLeechCount(int $userId): int
    {
        return (int) DB::table('peers')
            ->where('userid', $userId)
            ->where('seeder', 0)
            ->count();
    }

    public function getUnreadMessageCount(int $userId): int
    {
        return (int) DB::table('messages')
            ->where('receiver', $userId)
            ->where('unread', true)
            ->count();
    }

    public function getUnreadNewsCount(?string $lastHome): int
    {
        $query = DB::table('news')->where('notify', true);
        if (! empty($lastHome) && $lastHome !== '0000-00-00 00:00:00') {
            $query->where('added', '>', $lastHome);
        }

        return (int) $query->count();
    }

    public function getTotalReports(): int
    {
        return (int) DB::table('reports')->count();
    }

    public function getTotalCheaters(): int
    {
        return (int) DB::table('cheaters')->count();
    }

    public function getTorrentApprovalNoneCount(): int
    {
        return (int) DB::table('torrents')->where('approval_status', 0)->count();
    }

    public function getOpenComplaintsCount(): int
    {
        return (int) DB::table('complains')->where('answered', 0)->count();
    }

    public function getOpenReportsCount(): int
    {
        return (int) DB::table('reports')->where('dealtwith', 0)->count();
    }

    public function getOpenCheatersCount(): int
    {
        return (int) DB::table('cheaters')->where('dealtwith', 0)->count();
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
        DB::table('users')->where('id', $userId)->update($data);
    }

    /**
     * Record per-request user access data and precompute the main menu so
     * the header view helper does not have to mutate state or touch Redis.
     */
    public function prepareAccess(): void
    {
        $user = app(CurrentUser::class)->get();
        if ($user === null || empty($user['id'])) {
            return;
        }

        $script = RequestContext::instance()->getScript();
        if (in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            return;
        }

        app(UserUpdateBatch::class)->add('last_access', date('Y-m-d H:i:s'));
        app(UserUpdateBatch::class)->add('ip', $user['ip'] ?? Input::serverValue('REMOTE_ADDR', ''));

        IpLogRepository::saveToCache((int) $user['id']);

        $menuResult = Menu::render(
            $script,
            app(Language::class)->functions(),
            (string) app(Globals::class)->get('enableoffer', ''),
            null,
            $user,
            app(LegacyRedisCache::class),
            (string) app(Globals::class)->get('CURLANGDIR', ''),
        );

        app(Globals::class)->set('nexus_menu_html', $menuResult['html']);
        app(Globals::class)->set('nexus_menu_selected', $menuResult['selected']);

        if ((string) app(Globals::class)->get('where_tweak', '') === 'yes') {
            app(UserUpdateBatch::class)->add('page', $menuResult['selected']);
        }
    }

    /**
     * Flush any pending user updates to the database at the end of the request.
     */
    public function flushAccess(): void
    {
        $user = app(CurrentUser::class)->get();
        if ($user === null || empty($user['id'])) {
            return;
        }

        $userUpdateSet = app(UserUpdateBatch::class)->all();
        if (empty($userUpdateSet)) {
            return;
        }

        $this->updateUser((int) $user['id'], $userUpdateSet);
    }
}
