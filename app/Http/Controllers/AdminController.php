<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\AdminStatsRepository;
use App\Repositories\BonusRepository;
use App\Repositories\UserRepository;
use App\Support\Format;
use App\Support\Html;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class AdminController extends LegacyController
{
    public function donorlist(Request $request): View|RedirectResponse|Response
    {
        if (UserDisplay::currentClass() <= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0)) {
            return $this->legacyAbortResponse('Sorry', 'Access denied!');
        }

        $count = User::query()->where('donor', 'yes')->count();
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager(50, $count, 'donorlist.php?');

        $rows = User::query()
            ->where('donor', 'yes')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($rpp)
            ->get(['id', 'username', 'email', 'added', 'donated'])
            ->map(fn ($r) => $r->getAttributes());

        return $this->legacyPage($request, 'donorlist', true, [
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'users' => number_format($count),
        ]);

    }

    public function stats(Request $request): View|RedirectResponse|Response
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

    public function warned(Request $request): View|RedirectResponse|Response
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

    public function allagents(Request $request): View|RedirectResponse|Response
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/allagents.php' . ($qs ? '?' . $qs : ''));
        }

        return $this->legacyPage($request, 'allagents', true, ['agents' => AdminStatsRepository::allagents()]);
    }

    public function checkuser(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $langCheckuser = (array) SupportContext::getGlobal('lang_checkuser', []);

        $id = (int) (SupportContext::getQuery('id') ?? 0);
        if (! Validators::isId($id)) {
            return $this->legacyAbortResponse($langCheckuser['std_error'] ?? 'Error', $langCheckuser['std_no_user_id'] ?? 'No user with this ID.');
        }

        $userObj = User::query()->where('status', 'pending')->where('id', $id)->first();
        if (! $userObj) {
            return $this->legacyAbortResponse($langCheckuser['std_error'] ?? 'Error', $langCheckuser['std_no_user_id'] ?? 'No user with this ID.');
        }
        $user = $userObj->toArray();

        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (UserDisplay::currentClass() < $moderatorClass && (int) $user['invited_by'] !== $currentUserId) {
            return $this->legacyAbortResponse($langCheckuser['std_error'] ?? 'Error', $langCheckuser['std_no_permission'] ?? 'Permission denied.');
        }

        if ($user['gender'] === 'Male') {
            $gender = '<img class="male" src="pic/trans.gif" alt="Male" title="Male" style="margin-left: 4pt">';
        } elseif ($user['gender'] === 'Female') {
            $gender = '<img class="female" src="pic/trans.gif" alt="Female" title="Female" style="margin-left: 4pt">';
        } elseif ($user['gender'] === 'N/A') {
            $gender = '<img class="no_gender" src="pic/trans.gif" alt="N/A" title="No gender" style="margin-left: 4pt">';
        } else {
            $gender = '';
        }

        if ($user['added'] === '0000-00-00 00:00:00' || $user['added'] === null) {
            $joindate = 'N/A';
        } else {
            $joindate = $user['added'] . ' (' . Format::getElapsedTime(strtotime($user['added'])) . ' ago)';
        }

        $countryRow = NexusDB::table('countries')->where('id', $user['country'])->first(['name', 'flagpic']);
        $country = '';
        if ($countryRow) {
            $arr = (array) $countryRow;
            $country = "<td class=embedded><img src=pic/flag/{$arr['flagpic']} alt=\"{$arr['name']}\" style='margin-left: 8pt'></td>";
        }

        return $this->legacyPage($request, 'checkuser', true, [
            'id' => $id,
            'user' => $user,
            'gender' => $gender,
            'joindate' => $joindate,
            'country' => $country,
            'enabled' => $user['enabled'] === 'yes',
            'canSeeIp' => UserDisplay::currentClass() >= $moderatorClass && $user['ip'] !== '',
            'lang_checkuser' => $langCheckuser,
        ]);

    }

    public function takeconfirm(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeconfirm');

    }

    public function userBanLog(Request $request): View|RedirectResponse|Response
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

    public function clearCache(Request $request): View|RedirectResponse|Response
    {

        return $this->legacyPage($request, 'clearcache');

    }

    public function catmanage(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'catmanage');

    }

    public function fields(Request $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $field = new \Nexus\Field\Field();
        $langFields = (array) SupportContext::getGlobal('lang_fields', []);
        $langCatmanage = (array) SupportContext::getGlobal('lang_catmanage', []);
        if (empty($langCatmanage['row_custom_field_display_help'])) {
            $langCatmanage['row_custom_field_display_help'] = '';
            SupportContext::setGlobal('lang_catmanage', $langCatmanage);
        }
        $action = (string) (SupportContext::getQuery('action') ?? 'view');

        if ($action === 'submit') {
            try {
                $field->save(SupportContext::allRequest());
            } catch (\Exception $e) {
                return $this->legacyAbortResponse($langFields['field_management'] ?? 'Field management', $e->getMessage());
            }
            return redirect('fields.php?action=view');
        }

        if ($action === 'del') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if ($id <= 0) {
                return $this->legacyAbortResponse($langFields['field_management'] ?? 'Field management', 'Invalid id');
            }
            NexusDB::table('torrents_custom_fields')->where('id', $id)->delete();
            return redirect('fields.php?action=view');
        }

        if ($action === 'edit') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if ($id <= 0) {
                return $this->legacyAbortResponse($langFields['field_management'] ?? 'Field management', 'Invalid id');
            }
            $row = (array) NexusDB::table('torrents_custom_fields')->where('id', $id)->first();
            if (empty($row)) {
                return $this->legacyAbortResponse('', 'Invalid id');
            }
            return $this->legacyPage($request, 'fields', true, [
                'mode' => 'edit',
                'row' => $row,
                'lang_fields' => $langFields,
            ]);
        }

        if ($action === 'add') {
            return $this->legacyPage($request, 'fields', true, [
                'mode' => 'add',
                'lang_fields' => $langFields,
            ]);
        }

        return $this->legacyPage($request, 'fields', true, [
            'mode' => 'view',
            'fieldTable' => $field->buildFieldTable(),
            'lang_fields' => $langFields,
        ]);

    }

    public function formats(Request $request): View|RedirectResponse|Response
    {

        return $this->legacyPage($request, 'formats');

    }

    public function videoformats(Request $request): View|RedirectResponse|Response
    {

        return $this->legacyPage($request, 'videoformats');

    }

    public function settings(Request $request): RedirectResponse|Response
    {

        return $this->legacyPageWithRedirect($request, 'settings', true);

    }

    public function users(Request $request): View|RedirectResponse|Response
    {
        if (! Permissions::userCan(PermissionEnum::VIEW_USER_LIST->value, false, (int) (SupportContext::getUser()['id'] ?? 0))) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langUsers = (array) SupportContext::getGlobal('lang_users', []);
        $search = trim((string) (SupportContext::getQuery('search') ?? ''));
        $class = (string) (SupportContext::getQuery('class') ?? '-');
        $country = (int) (SupportContext::getQuery('country') ?? 0);
        $letter = trim((string) (SupportContext::getQuery('letter') ?? ''));

        if (strlen($letter) > 1) {
            return $this->legacyAbortResponse('Error', 'Invalid letter.');
        }

        if (! \App\Support\User::isValidUserClass($class)) {
            $class = '-';
        }

        $q = '';
        if ($search !== '' && $letter === '') {
            $q = 'search=' . rawurlencode($search);
        } elseif ($letter !== '' && strpos('0abcdefghijklmnopqrstuvwxyz', $letter) !== false) {
            $q = "letter={$letter}";
        }

        if ($class !== '-') {
            $q .= ($q ? '&' : '') . "class={$class}";
        }
        if ($country > 0) {
            $q .= ($q ? '&' : '') . "country={$country}";
        }

        $classOptions = [];
        for ($i = 0;; $i++) {
            $c = UserClass::name($i, false, true, true);
            if (! $c) {
                break;
            }
            $classOptions[] = ['value' => $i, 'label' => $c, 'selected' => $class !== '-' && $class == $i];
        }

        $countryOptions = [['value' => 0, 'label' => $langUsers['select_any_country'] ?? 'Any country', 'selected' => $country === 0]];
        foreach (\App\Repositories\UserListingRepository::getCountries() as $ct) {
            $countryOptions[] = ['value' => (int) $ct['id'], 'label' => (string) $ct['name'], 'selected' => $country === (int) $ct['id']];
        }

        $perPage = 50;
        $filters = ['search' => $search, 'class' => $class, 'country' => $country, 'letter' => $letter];
        $count = \App\Repositories\UserListingRepository::countUsers($filters);
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perPage, $count, 'users.php?' . $q . ($q ? '&' : ''));
        $userRows = \App\Repositories\UserListingRepository::listUsers($filters, (int) $offset, $perPage);

        $rows = [];
        foreach ($userRows as $arr) {
            $rows[] = [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'added' => $arr['added'],
                'last_access' => $arr['last_access'],
                'class_name' => UserClass::name((int) $arr['class'], false, true, true),
                'country' => $arr['country'],
            ];
        }

        return $this->legacyPage($request, 'users', true, [
            'lang_users' => $langUsers,
            'search' => $search,
            'class' => $class,
            'country' => $country,
            'letter' => $letter,
            'classOptions' => $classOptions,
            'countryOptions' => $countryOptions,
            'pagerParam' => $q,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

    public function location(Request $request): View|RedirectResponse|Response
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        $actionUrl = 'location.php';
        $perpage = 50;
        $success = false;
        $error = '';
        $editRow = [];
        $mode = 'list';
        $message = '';

        $rangeStartIp = (string) (SupportContext::getQuery('range_start_ip') ?? '');
        $rangeEndIp = (string) (SupportContext::getQuery('range_end_ip') ?? '');
        $hasRangeFilter = false;

        $sure = (string) (SupportContext::getQuery('sure') ?? '');
        $delid = (int) (SupportContext::getQuery('delid') ?? 0);
        if ($sure === 'yes' && $delid > 0) {
            if (Validators::isId($delid)) {
                NexusDB::table('locations')->where('id', $delid)->delete();
            }
            return $this->legacyAbortResponse('Success', 'Location successfully removed, click <a class=altlink href="' . $actionUrl . '">here</a> to go back.', false);
        }

        if ($delid > 0) {
            return $this->legacyAbortResponse('Confirm', 'Are you sure you would like to delete this Location?(<strong><a href="' . $actionUrl . '?delid=' . $delid . '&sure=yes">Yes!</a></strong> / <strong><a href="' . $actionUrl . '">No</a></strong>)', false);
        }

        $edited = (string) (SupportContext::getQuery('edited') ?? '');
        if ($edited === '1') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $name = (string) SupportContext::getQuery('name');
            $flagpic = (string) SupportContext::getQuery('flagpic');
            $locationMain = (string) SupportContext::getQuery('location_main');
            $locationSub = (string) SupportContext::getQuery('location_sub');
            $startIp = (string) SupportContext::getQuery('start_ip');
            $endIp = (string) SupportContext::getQuery('end_ip');
            $theoryUpspeed = (string) SupportContext::getQuery('theory_upspeed');
            $practicalUpspeed = (string) SupportContext::getQuery('practical_upspeed');
            $theoryDownspeed = (string) SupportContext::getQuery('theory_downspeed');
            $practicalDownspeed = (string) SupportContext::getQuery('practical_downspeed');

            if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
                $error = 'Invalid IP Address Format !!!';
            } elseif (ip2long($endIp) <= ip2long($startIp)) {
                $error = 'The end IP address should be larger than the start one, or equal for single IP check!';
            } elseif (Validators::isId($id)) {
                NexusDB::table('locations')->where('id', $id)->update([
                    'name' => $name,
                    'flagpic' => $flagpic,
                    'location_main' => $locationMain,
                    'location_sub' => $locationSub,
                    'start_ip' => $startIp,
                    'end_ip' => $endIp,
                    'theory_upspeed' => $theoryUpspeed,
                    'practical_upspeed' => $practicalUpspeed,
                    'theory_downspeed' => $theoryDownspeed,
                    'practical_downspeed' => $practicalDownspeed,
                ]);
                return $this->legacyAbortResponse('Success!', 'Location has been edited, click <a class=altlink href="' . $actionUrl . '">here</a> to go back', false);
            }
        }

        $editid = (int) (SupportContext::getQuery('editid') ?? 0);
        if ($editid > 0) {
            $editRow = (array) NexusDB::table('locations')->where('id', $editid)->first();
            if (empty($editRow)) {
                $error = 'Location not found.';
            } else {
                $mode = 'edit';
                return $this->legacyPage($request, 'location', true, [
                    'mode' => $mode,
                    'editRow' => $editRow,
                ]);
            }
        }

        $add = (string) (SupportContext::getQuery('add') ?? '');
        if ($add === 'true') {
            $name = (string) SupportContext::getQuery('name');
            $flagpic = (string) SupportContext::getQuery('flagpic');
            $locationMain = (string) SupportContext::getQuery('location_main');
            $locationSub = (string) SupportContext::getQuery('location_sub');
            $startIp = (string) SupportContext::getQuery('start_ip');
            $endIp = (string) SupportContext::getQuery('end_ip');
            $theoryUpspeed = (string) SupportContext::getQuery('theory_upspeed');
            $practicalUpspeed = (string) SupportContext::getQuery('practical_upspeed');
            $theoryDownspeed = (string) SupportContext::getQuery('theory_downspeed');
            $practicalDownspeed = (string) SupportContext::getQuery('practical_downspeed');

            if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
                $error = 'Invalid IP Address Format !!!';
            } elseif (ip2long($endIp) <= ip2long($startIp)) {
                $error = 'The end IP address should be larger than the start one, or equal for single IP check!';
            } else {
                NexusDB::table('locations')->insert([
                    'name' => $name,
                    'flagpic' => $flagpic,
                    'location_main' => $locationMain,
                    'location_sub' => $locationSub,
                    'start_ip' => $startIp,
                    'end_ip' => $endIp,
                    'theory_upspeed' => $theoryUpspeed,
                    'practical_upspeed' => $practicalUpspeed,
                    'theory_downspeed' => $theoryDownspeed,
                    'practical_downspeed' => $practicalDownspeed,
                ]);
                $success = true;
            }
        }

        $checkRange = (string) (SupportContext::getQuery('check_range') ?? '');
        if ($checkRange === 'true') {
            if (! Network::isValidIpv4Format($rangeStartIp) || ! Network::isValidIpv4Format($rangeEndIp)) {
                $error = 'Invalid IP Address Format !!!';
            } elseif (ip2long($rangeEndIp) <= ip2long($rangeStartIp)) {
                $error = 'The end IP Address should be larger than the start one, or equal for single IP check!';
            } else {
                $hasRangeFilter = true;
                $message = 'Conforming Locations:';
            }
        }

        $baseQuery = NexusDB::table('locations')
            ->when($hasRangeFilter, function ($query) use ($rangeStartIp, $rangeEndIp) {
                $start = (int) ip2long($rangeStartIp);
                $end = (int) ip2long($rangeEndIp);
                return $query->whereRaw("INET_ATON(start_ip) <= {$start} AND INET_ATON(end_ip) >= {$end}");
            });

        $count = $baseQuery->count();
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, 'location.php?');

        $locations = (clone $baseQuery)
            ->orderBy('name')
            ->orderBy('start_ip')
            ->offset($offset)
            ->limit($rpp)
            ->get();

        $rows = [];
        foreach ($locations as $loc) {
            $row = (array) $loc;
            $row['flagpic_url'] = $row['flagpic'] !== '' ? asset('pic/location/' . $row['flagpic']) : '';
            $countSub = strlen((string) $row['location_sub']);
            if ($countSub > 40) {
                $row['location_sub'] = substr((string) $row['location_sub'], 0, 40) . '..';
            }
            $rows[] = $row;
        }

        return $this->legacyPage($request, 'location', true, [
            'mode' => $mode,
            'success' => $success,
            'error' => $error,
            'message' => $message,
            'rangeStartIp' => $rangeStartIp,
            'rangeEndIp' => $rangeEndIp,
            'hasRangeFilter' => $hasRangeFilter,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'actionUrl' => $actionUrl,
        ]);

    }

    public function reset(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied, Administrator Only.');
        }

        $curUser = SupportContext::getUser() ?? [];
        $currentUsername = (string) ($curUser['username'] ?? '');

        $success = false;
        $message = '';

        if ($request->isMethod('post')) {
            $username = trim((string) SupportContext::getPost('username'));
            $newpassword = trim((string) SupportContext::getPost('newpassword'));
            $newpasswordagain = trim((string) SupportContext::getPost('newpasswordagain'));

            if ($username === '' || $newpassword === '' || $newpasswordagain === '') {
                return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
            }

            if ($newpassword !== $newpasswordagain) {
                return $this->legacyAbortResponse('Error', "The passwords didn't match! Must've typoed. Try again.");
            }

            if (strlen($newpassword) < 6) {
                return $this->legacyAbortResponse('Error', 'Sorry, password is too short (min is 6 chars)');
            }

            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                return $this->legacyAbortResponse('Error', "Sorry, that username doesn't exist.");
            }
            $arr = $user->toArray();

            if (UserDisplay::currentClass() <= (int) $arr['class']) {
                $log = "Password Reset For {$username} by {$currentUsername} denied: operator class => " . UserDisplay::currentClass() . " is not greater than target user => {$arr['class']}";
                Log::writeWithContext($log);
                Logger::writeWithContext($log, 'alert', false);
                return $this->legacyAbortResponse('Error', "Sorry, you don't have enough permission to reset this user's password.");
            }

            $userRep = new UserRepository();
            try {
                $userRep->resetPassword((int) $arr['id'], $newpassword, $newpasswordagain);
            } catch (\Exception $e) {
                return $this->legacyAbortResponse('Error', $e->getMessage());
            }

            Log::writeWithContext("Password Reset For {$username} by {$currentUsername}");
            $success = true;
            $message = "The password of account <b>{$username}</b> is reset, please inform user of this change.";
        }

        return $this->legacyPage($request, 'reset', true, [
            'success' => $success,
            'message' => $message,
        ]);

    }

    public function selfEnable(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $currentUsername = (string) ($curUser['username'] ?? '');

        $title = Locale::trans('self-enable.title', [], null);
        $unit = Setting::getSelfEnableBonus();

        $viewData = [
            'title' => $title,
            'unit' => $unit,
            'enabled' => ($curUser['enabled'] ?? '') === 'yes',
            'bonus' => (float) ($curUser['seedbonus'] ?? 0),
            'latestBanLog' => null,
            'elapsedDay' => 0,
            'total' => 0,
            'isUserBonusEnough' => false,
            'insufficientMessage' => '',
        ];

        if ($unit <= 0) {
            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        if (($curUser['enabled'] ?? '') === 'yes') {
            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLog = UserBanLog::query()->where('uid', $currentUserId)->orderByDesc('id')->first();
        if (! $latestBanLog) {
            $viewData['latestBanLog'] = null;
            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        $elapsedDay = (int) ceil((time() - $latestBanLog->created_at->getTimestamp()) / 86400);
        $total = $unit * $elapsedDay;
        $isUserBonusEnough = (float) ($curUser['seedbonus'] ?? 0) >= $total;
        $insufficientMessage = Locale::trans('self-enable.bonus_not_enough', ['bonus' => $curUser['seedbonus'] ?? 0], null);

        if (! empty(SupportContext::getPost('submit'))) {
            if (! $isUserBonusEnough) {
                $viewData['latestBanLog'] = $latestBanLog;
                $viewData['elapsedDay'] = $elapsedDay;
                $viewData['total'] = $total;
                $viewData['isUserBonusEnough'] = false;
                $viewData['insufficientMessage'] = $insufficientMessage;
                return $this->legacyPage($request, 'self-enable', true, $viewData);
            }

            $userRep = new UserRepository();
            $bonusRep = new BonusRepository();
            $operator = User::query()->find($currentUserId);
            if ($operator) {
                $bonusRep->consumeUserBonus($currentUserId, $total, BonusLogs::BUSINESS_TYPE_SELF_ENABLE, $title);
                $userRep->enableUser($operator, $currentUserId, $title);
            }

            return redirect('index.php');
        }

        $viewData['latestBanLog'] = $latestBanLog;
        $viewData['elapsedDay'] = $elapsedDay;
        $viewData['total'] = $total;
        $viewData['isUserBonusEnough'] = $isUserBonusEnough;
        $viewData['insufficientMessage'] = $insufficientMessage;

        return $this->legacyPage($request, 'self-enable', true, $viewData);

    }

    public function unco(Request $request): View|RedirectResponse|Response
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

    public function adduser(Request $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        if ($request->isMethod('post')) {
            $userRep = new UserRepository();
            try {
                $newUser = $userRep->store([
                    'username' => SupportContext::getPost('username'),
                    'email' => SupportContext::getPost('email'),
                    'password' => SupportContext::getPost('password'),
                    'password_confirmation' => SupportContext::getPost('password2'),
                ]);
            } catch (\Exception $e) {
                return $this->legacyAbortResponse('ERROR', $e->getMessage());
            }

            return redirect('userdetails.php?id=' . (int) $newUser->id);
        }

        return $this->legacyPage($request, 'adduser', true);

    }

    public function testip(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied');
        }

        $langTestip = (array) SupportContext::getGlobal('lang_testip', []);

        if ($request->isMethod('post')) {
            $ip = (string) SupportContext::getPost('ip');
        } else {
            $ip = (string) (SupportContext::getQuery('ip') ?? '');
        }

        $message = '';
        $banstable = '';
        $hasResult = false;

        if ($ip !== '') {
            $nip = ip2long($ip);
            if ($nip === false || $nip === -1) {
                return $this->legacyAbortResponse('Error', 'Bad IP.');
            }
            $rows = NexusDB::table('bans')->where('first', '<=', $nip)->where('last', '>=', $nip)->get();
            if ($rows->isEmpty()) {
                $message = 'The IP address <b>' . htmlspecialchars($ip) . '</b> is not banned.';
                $hasResult = true;
            } else {
                $hasResult = true;
                $message = 'The IP address <b>' . $ip . '</b> is banned:';
                $banstable = "<table class=main border=0 cellspacing=0 cellpadding=5>\n" .
                    "<tr><td class=colhead>First</td><td class=colhead>Last</td><td class=colhead>Comment</td></tr>\n";
                foreach ($rows as $row) {
                    $arr = (array) $row;
                    $first = long2ip($arr['first']);
                    $last = long2ip($arr['last']);
                    $comment = htmlspecialchars((string) $arr['comment']);
                    $banstable .= "<tr><td>$first</td><td>$last</td><td>$comment</td></tr>\n";
                }
                $banstable .= '</table>\n';
            }
        }

        return $this->legacyPage($request, 'testip', true, [
            'ip' => $ip,
            'message' => $message,
            'banstable' => $banstable,
            'hasResult' => $hasResult,
            'lang_testip' => $langTestip,
        ]);

    }

}