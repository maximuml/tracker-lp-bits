<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\BonusRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\UserListingRepository;
use App\Repositories\UserRepository;
use App\Support\Html;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class AdminController extends LegacyController
{
    private ModerationRepository $moderationRepository;

    public function __construct(ModerationRepository $moderationRepository)
    {
        $this->moderationRepository = $moderationRepository;
    }

    public function userBanLog(Request $request): View|RedirectResponse|Response
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();

            return redirect('/user-ban-log.php'.($qs ? '?'.$qs : ''));
        }

        $qRaw = is_scalar($request->input('q', '')) ? (string) $request->input('q', '') : '';
        $q = htmlspecialchars($qRaw);

        $query = UserBanLog::query();
        if (! empty($q)) {
            $query->where('username', 'like', "%{$q}%");
        }
        $total = (int) (clone $query)->count();
        $perPage = 50;
        [$paginationTop, $paginationBottom, $limit, $offset] = Pagination::pager($perPage, $total, '?');
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
        $table = Html::buildTable($header, $rows);

        return $this->legacyPage($request, 'user-ban-log', true, [
            'q' => $q,
            'table' => $table,
            'paginationTop' => $paginationTop,
            'paginationBottom' => $paginationBottom,
        ]);
    }

    public function clearCache(Request $request): View|RedirectResponse|Response
    {
        if (UserDisplay::currentClass() < (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $done = false;
        $error = '';
        if ($request->isMethod('post')) {
            $cachename = (string) $request->input('cachename', '');
            if ($cachename === '') {
                $error = 'You must fill in cache name.';
            } else {
                $multilang = $request->input('multilang') === 'yes';
                $cache = SupportContext::getCache();
                if ($cache !== null) {
                    $cache->delete_value($cachename, $multilang);
                }
                $done = true;
            }
        }

        return $this->legacyPage($request, 'clearcache', true, [
            'done' => $done,
            'error' => $error,
        ]);
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
            $q = 'search='.rawurlencode($search);
        } elseif ($letter !== '' && strpos('0abcdefghijklmnopqrstuvwxyz', $letter) !== false) {
            $q = "letter={$letter}";
        }

        if ($class !== '-') {
            $q .= ($q ? '&' : '')."class={$class}";
        }
        if ($country > 0) {
            $q .= ($q ? '&' : '')."country={$country}";
        }

        $classOptions = [];
        for ($i = 0; ; $i++) {
            $c = UserClass::name($i, false, true, true);
            if (! $c) {
                break;
            }
            $classOptions[] = ['value' => $i, 'label' => $c, 'selected' => $class !== '-' && $class == $i];
        }

        $countryOptions = [['value' => 0, 'label' => $langUsers['select_any_country'] ?? 'Any country', 'selected' => $country === 0]];
        foreach (UserListingRepository::getCountries() as $ct) {
            $countryOptions[] = ['value' => (int) $ct['id'], 'label' => (string) $ct['name'], 'selected' => $country === (int) $ct['id']];
        }

        $perPage = 50;
        $filters = ['search' => $search, 'class' => $class, 'country' => $country, 'letter' => $letter];
        $count = UserListingRepository::countUsers($filters);
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perPage, $count, 'users.php?'.$q.($q ? '&' : ''));
        $userRows = UserListingRepository::listUsers($filters, (int) $offset, $perPage);

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

            return $this->legacyAbortResponse('Success', 'Location successfully removed, click <a class=altlink href="'.$actionUrl.'">here</a> to go back.', false);
        }

        if ($delid > 0) {
            return $this->legacyAbortResponse('Confirm', 'Are you sure you would like to delete this Location?(<strong><a href="'.$actionUrl.'?delid='.$delid.'&sure=yes">Yes!</a></strong> / <strong><a href="'.$actionUrl.'">No</a></strong>)', false);
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

                return $this->legacyAbortResponse('Success!', 'Location has been edited, click <a class=altlink href="'.$actionUrl.'">here</a> to go back', false);
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
            $row['flagpic_url'] = $row['flagpic'] !== '' ? asset('pic/location/'.$row['flagpic']) : '';
            $countSub = strlen((string) $row['location_sub']);
            if ($countSub > 40) {
                $row['location_sub'] = substr((string) $row['location_sub'], 0, 40).'..';
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
                $log = "Password Reset For {$username} by {$currentUsername} denied: operator class => ".UserDisplay::currentClass()." is not greater than target user => {$arr['class']}";
                Log::writeWithContext($log);
                Logger::writeWithContext($log, 'alert', false);

                return $this->legacyAbortResponse('Error', "Sorry, you don't have enough permission to reset this user's password.");
            }

            $userRep = new UserRepository;
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

        $latestBanLogCreatedAt = $latestBanLog->created_at;
        $elapsedDay = $latestBanLogCreatedAt instanceof Carbon
            ? (int) ceil((time() - $latestBanLogCreatedAt->getTimestamp()) / 86400)
            : 0;
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

            $userRep = new UserRepository;
            $bonusRep = new BonusRepository;
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

            return redirect('/unco.php'.($qs ? '?'.$qs : ''));
        }

        $status = SupportContext::getQuery('status');
        if ($status) {
            LegacyResponse::assertId($status, true);
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
            $userRep = new UserRepository;
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

            return redirect('userdetails.php?id='.(int) $newUser->id);
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
            $rows = $this->moderationRepository->findMatchingBans((int) $nip);
            if (empty($rows)) {
                $message = 'The IP address <b>'.htmlspecialchars($ip).'</b> is not banned.';
                $hasResult = true;
            } else {
                $hasResult = true;
                $message = 'The IP address <b>'.$ip.'</b> is banned:';
                $banstable = "<table class=main border=0 cellspacing=0 cellpadding=5>\n".
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
