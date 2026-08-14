<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        return $this->legacyPage($request, 'warned');

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

        return $this->legacyPage($request, 'user-ban-log');

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

        return $this->legacyPage($request, 'unco', true);

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