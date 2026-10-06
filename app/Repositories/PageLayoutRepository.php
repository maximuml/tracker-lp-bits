<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Models\Invite;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\Menu;
use App\Support\PageState;
use App\Support\RequestContext;
use App\Support\UserUpdateBatch;
use Illuminate\Support\Facades\DB;

class PageLayoutRepository extends BaseRepository implements PageLayoutRepositoryInterface
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly PageState $pageState,
        private readonly IpLogRepository $ipLogRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache,
        private readonly UserUpdateBatch $userUpdateBatch,
        private readonly RequestContext $requestContext,
        private readonly Menu $menu = new Menu,
    ) {}

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

    /**
     * @return array{seed: int, leech: int}
     */
    public function getActivePeerCounts(int $userId): array
    {
        $rows = DB::table('peers')
            ->where('userid', $userId)
            ->select('seeder', DB::raw('count(*) as cnt'))
            ->groupBy('seeder')
            ->pluck('cnt', 'seeder');

        return [
            'seed' => (int) ($rows[1] ?? 0),
            'leech' => (int) ($rows[0] ?? 0),
        ];
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
        $user = $this->currentUser->get();
        if ($user === null || empty($this->currentUser->id())) {
            return;
        }

        $script = $this->requestContext->getScript();
        if (in_array($script, ['announce', 'scrape', 'torrentrss', 'download'], true)) {
            return;
        }

        $this->userUpdateBatch->add('last_access', date('Y-m-d H:i:s'));
        $this->userUpdateBatch->add('ip', $this->currentUser->value('ip', Input::serverValue('REMOTE_ADDR', '')));

        $this->ipLogRepository->saveToCache((int) $this->currentUser->id());

        $menuResult = $this->menu->render(
            $script,
            SiteConfig::current()->main->showOffer() ? 'yes' : 'no',
            null,
            $user,
            $this->legacyRedisCache,
            $this->pageState->langDir(),
        );

        $this->pageState->setMenu($menuResult['html'], $menuResult['selected']);

        if (SiteConfig::current()->tweak->where() === 'yes') {
            $this->userUpdateBatch->add('page', $menuResult['selected']);
        }
    }

    /**
     * Flush any pending user updates to the database at the end of the request.
     */
    public function flushAccess(): void
    {
        $user = $this->currentUser->get();
        if ($user === null || empty($this->currentUser->id())) {
            return;
        }

        $userUpdateSet = $this->userUpdateBatch->all();
        if (empty($userUpdateSet)) {
            return;
        }

        $this->updateUser((int) $this->currentUser->id(), $userUpdateSet);
    }
}
