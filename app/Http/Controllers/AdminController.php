<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\AdminStatsRepository;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends LegacyController
{
    public function donorlist(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'donorlist');

    }

    public function stats(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/stats.php' . ($qs ? '?' . $qs : ''));
        }

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            abort(403);
        }

        $uporder = is_scalar($request->query('uporder', '')) ? (string) $request->query('uporder', '') : '';
        $catorder = is_scalar($request->query('catorder', '')) ? (string) $request->query('catorder', '') : '';

        $data = AdminStatsRepository::stats($uporder, $catorder);
        $data['php_self'] = SupportContext::getServerValue('PHP_SELF');

        return $this->legacyPage($request, 'stats', true, $data);
    }

    public function warned(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/warned.php' . ($qs ? '?' . $qs : ''));
        }

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            abort(403);
        }

        $count = (int) User::query()->where('warned', 'yes')->count();
        $rows = User::query()
            ->where('warned', 1)
            ->where('enabled', 'yes')
            ->orderByRaw('(uploaded/downloaded)')
            ->get()
            ->map(fn ($r) => $r->getAttributes())
            ->toArray();

        return $this->legacyPage($request, 'warned', true, [
            'count' => $count,
            'warnedCount' => number_format($count),
            'rows' => $rows,
        ]);
    }

    public function nowarn(Request $request): Response|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/nowarn.php' . ($qs ? '?' . $qs : ''));
        }

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            abort(403);
        }

        if ($request->input('nowarned') === 'nowarned') {
            $usernw = (array) $request->input('usernw', []);
            $desact = (array) $request->input('desact', []);
            $delete = (array) $request->input('delete', []);

            if (empty($usernw) && empty($desact) && empty($delete)) {
                abort(400, 'You Must Select A User To Edit.');
            }

            $modcomment = date('Y-m-d') . ' - Warning Removed By ' . (SupportContext::getUser()['username'] ?? '');

            if (! empty($usernw)) {
                $userIds = array_values(array_filter(array_map('intval', $usernw)));
                if (! empty($userIds)) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    DB::update(
                        "UPDATE users SET warned = 'no', warneduntil = NULL, modcomment = IF(modcomment = '', ?, CONCAT_WS('\\n', ?, modcomment)) WHERE id IN ({$placeholders})",
                        array_merge([$modcomment, $modcomment], $userIds)
                    );
                }
            }

            if (! empty($desact)) {
                $desactIds = array_values(array_filter(array_map('intval', $desact)));
                if (! empty($desactIds)) {
                    User::query()->whereIn('id', $desactIds)->update(['enabled' => 'no']);
                }
            }
        }

        return redirect('/warned.php');
    }

    public function allagents(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/allagents.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPage($request, 'allagents', true, ['agents' => AdminStatsRepository::allagents()]);
    }

    public function checkuser(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'checkuser');

    }

    public function takeconfirm(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeconfirm');

    }

    public function userBanLog(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/user-ban-log.php' . ($qs ? '?' . $qs : ''));
        }

        $qRaw = is_scalar($request->input('q', '')) ? (string) $request->input('q', '') : '';
        $q = htmlspecialchars($qRaw);

        $query = UserBanLog::query();
        if (! empty($q)) {
            $query->where('username', 'like', "%{$q}%");
        }
        $total = (int) (clone $query)->count();
        $perPage = 50;
        [$paginationTop, $paginationBottom, $limit, $offset] = \App\Support\Pagination::pager($perPage, $total, '?');
        $rows = (clone $query)
            ->offset($offset)
            ->take($perPage)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        $header = [
            'id' => 'ID',
            'uid' => 'UID',
            'username' => 'Username',
            'reason' => 'Reason',
            'created_at' => 'Created at',
        ];
        $table = \App\Support\Html::buildTable($header, $rows);

        return $this->legacyPage($request, 'user-ban-log', true, [
            'q' => $q,
            'table' => $table,
            'paginationTop' => $paginationTop,
            'paginationBottom' => $paginationBottom,
        ]);
    }

    public function clearCache(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'clearcache');

    }

    public function catmanage(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'catmanage');

    }

    public function fields(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'fields');

    }

    public function formats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'formats');

    }

    public function videoformats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'videoformats');

    }

    public function settings(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'settings', true);

    }

    public function users(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'users', true);

    }

    public function location(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'location', true);

    }

    public function reset(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'reset', true);

    }

    public function selfEnable(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'self-enable', true);

    }

    public function unco(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/unco.php' . ($qs ? '?' . $qs : ''));
        }

        $status = SupportContext::getQuery('status');
        if ($status) {
            \App\Support\LegacyResponse::assertId($status, true);
        }

        $rows = User::query()
            ->where('status', 'pending')
            ->orderBy('username')
            ->get()
            ->map(fn ($user) => $user->getAttributes())
            ->toArray();

        return $this->legacyPage($request, 'unco', true, [
            'status' => $status,
            'rows' => $rows,
        ]);
    }

    public function adduser(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'adduser', true);

    }

    public function testip(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'testip', true);

    }

}