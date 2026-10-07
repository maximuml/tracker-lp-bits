<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClearCacheRequest;
use App\Http\Requests\LocationPostRequest;
use App\Http\Requests\TestIpRequest;
use App\Http\Requests\UserBanLogRequest;
use App\Repositories\ModerationRepository;
use App\Repositories\UserModerationRepository;
use App\Services\LocationService;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminToolsController extends LegacyController
{
    public function __construct(private readonly UserModerationRepository $userModerationRepository,
        private readonly ModerationRepository $moderationRepository,
        private readonly LocationService $locationService,
        private readonly CurrentUser $currentUser,
        private readonly ?NexusCache $cache,
    ) {}

    public function userBanLog(Request $request): View|RedirectResponse|Response
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/user-ban-log'.($qs ? '?'.$qs : ''));
        }

        $qRaw = is_scalar($request->input('q', '')) ? (string) $request->input('q', '') : '';
        $q = htmlspecialchars($qRaw);

        $queryParam = $q === '' ? null : $q;
        $total = $this->userModerationRepository->countBanLogs($queryParam);
        $perPage = 50;
        [$paginationTop, $paginationBottom, $limit, $offset] = Pagination::pager($perPage, $total, '?');
        $rows = $this->userModerationRepository->listBanLogs($queryParam, $offset, $perPage)
            ->toArray();

        $header = [
            'id' => 'ID',
            'uid' => 'UID',
            'username' => 'Username',
            'reason' => 'Reason',
            'created_at' => 'Created at',
        ];

        return $this->legacyPage($request, 'user-ban-log', true, [
            'q' => $q,
            'header' => $header,
            'rows' => $rows,
            'paginationTop' => $paginationTop,
            'paginationBottom' => $paginationBottom,
            'serverRequestUri' => Input::serverValue('REQUEST_URI'),
        ]);
    }

    public function userBanLogPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/admin/user-ban-log'.$suffix, 308);
    }

    public function userBanLogSubmit(UserBanLogRequest $request): View|RedirectResponse|Response
    {
        return $this->userBanLog($request);
    }

    public function clearCache(Request $request): View|RedirectResponse|Response
    {
        if (UserDisplay::currentClass() < (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $done = false;
        $error = '';

        return $this->legacyPage($request, 'clearcache', true, [
            'done' => $done,
            'error' => $error,
        ]);
    }

    public function clearCachePost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/system/clear-cache'.$suffix, 308);
    }

    public function clearCacheSubmit(ClearCacheRequest $request): View|RedirectResponse|Response
    {
        if (UserDisplay::currentClass() < (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $done = false;
        $error = '';
        $cachename = (string) $request->input('cachename', '');
        if ($cachename === '') {
            $error = 'You must fill in cache name.';
        } else {
            $multilang = $request->input('multilang') === 'yes';
            $cache = $this->cache;
            if ($cache !== null) {
                $cache->forget($cachename, $multilang);
            }
            $done = true;
        }

        return $this->legacyPage($request, 'clearcache', true, [
            'done' => $done,
            'error' => $error,
        ]);
    }

    public function location(Request $request): View|RedirectResponse|Response
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        $actionUrl = '/web/location';
        $success = false;
        $error = '';
        $editRow = [];
        $mode = 'list';

        $rangeStartIp = (string) (request()->query('range_start_ip') ?? '');
        $rangeEndIp = (string) (request()->query('range_end_ip') ?? '');

        $sure = (string) (request()->query('sure') ?? '');
        $delid = (int) (request()->query('delid') ?? 0);
        if ($sure === 'yes' && $delid > 0) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        if ($delid > 0) {
            return $this->legacyAbortResponse('Confirm', 'Are you sure you would like to delete this Location?(<strong><a href="'.$actionUrl.'?delid='.$delid.'&sure=yes">Yes!</a></strong> / <strong><a href="'.$actionUrl.'">No</a></strong>)', false);
        }

        $edited = (string) (request()->query('edited') ?? '');
        if ($edited === '1') {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $editid = (int) (request()->query('editid') ?? 0);
        if ($editid > 0) {
            $editRow = $this->locationService->findLocation($editid);
            if (empty($editRow)) {
                $error = 'Location not found.';
            } else {
                $mode = 'edit';

                return $this->legacyPage($request, 'location', true, [
                    'mode' => $mode,
                    'editRow' => $editRow,
                    'actionUrl' => $actionUrl,
                ]);
            }
        }

        $add = (string) (request()->query('add') ?? '');
        if ($add === 'true') {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        return $this->renderLocationList($request, $success, $error, $rangeStartIp, $rangeEndIp);
    }

    public function locationPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/system/location'.$suffix, 308);
    }

    public function locationSubmit(LocationPostRequest $request): View|RedirectResponse|Response
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        $actionUrl = '/web/location';
        $success = false;
        $error = '';

        $rangeStartIp = (string) (request()->query('range_start_ip') ?? '');
        $rangeEndIp = (string) (request()->query('range_end_ip') ?? '');

        $sure = (string) (request()->query('sure') ?? '');
        $delid = (int) (request()->query('delid') ?? 0);
        if ($sure === 'yes' && $delid > 0) {
            if (Validators::isId($delid)) {
                $this->locationService->deleteLocation($delid);
            }

            return $this->legacyAbortResponse('Success', 'Location successfully removed, click <a class=altlink href="'.$actionUrl.'">here</a> to go back.', false);
        }

        $edited = (string) (request()->query('edited') ?? '');
        if ($edited === '1') {
            $id = (int) (request()->query('id') ?? 0);
            $name = (string) request()->query('name');
            $flagpic = (string) request()->query('flagpic');
            $locationMain = (string) request()->query('location_main');
            $locationSub = (string) request()->query('location_sub');
            $startIp = (string) request()->query('start_ip');
            $endIp = (string) request()->query('end_ip');
            $theoryUpspeed = (string) request()->query('theory_upspeed');
            $practicalUpspeed = (string) request()->query('practical_upspeed');
            $theoryDownspeed = (string) request()->query('theory_downspeed');
            $practicalDownspeed = (string) request()->query('practical_downspeed');

            if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
                $error = 'Invalid IP Address Format !!!';
            } elseif (ip2long($endIp) <= ip2long($startIp)) {
                $error = 'The end IP address should be larger than the start one, or equal for single IP check!';
            } elseif (Validators::isId($id)) {
                $this->locationService->updateLocation($id, [
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

            return $this->renderLocationList($request, $success, $error, $rangeStartIp, $rangeEndIp);
        }

        $add = (string) (request()->query('add') ?? '');
        if ($add === 'true') {
            $name = (string) request()->query('name');
            $flagpic = (string) request()->query('flagpic');
            $locationMain = (string) request()->query('location_main');
            $locationSub = (string) request()->query('location_sub');
            $startIp = (string) request()->query('start_ip');
            $endIp = (string) request()->query('end_ip');
            $theoryUpspeed = (string) request()->query('theory_upspeed');
            $practicalUpspeed = (string) request()->query('practical_upspeed');
            $theoryDownspeed = (string) request()->query('theory_downspeed');
            $practicalDownspeed = (string) request()->query('practical_downspeed');

            if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
                $error = 'Invalid IP Address Format !!!';
            } elseif (ip2long($endIp) <= ip2long($startIp)) {
                $error = 'The end IP address should be larger than the start one, or equal for single IP check!';
            } else {
                $this->locationService->createLocation([
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

            return $this->renderLocationList($request, $success, $error, $rangeStartIp, $rangeEndIp);
        }

        return $this->location($request);
    }

    private function renderLocationList(Request $request, bool $success, string $error, string $rangeStartIp, string $rangeEndIp): View|RedirectResponse
    {
        $actionUrl = '/web/location';
        $perpage = 50;
        $hasRangeFilter = false;
        $message = '';

        $checkRange = (string) (request()->query('check_range') ?? '');
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

        $rangeStartInt = $hasRangeFilter ? (int) ip2long($rangeStartIp) : null;
        $rangeEndInt = $hasRangeFilter ? (int) ip2long($rangeEndIp) : null;

        $count = $this->locationService->countLocations($rangeStartInt, $rangeEndInt);
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, '/web/location?');

        $locations = $this->locationService->listLocations($offset, $rpp, $rangeStartInt, $rangeEndInt);

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
            'mode' => 'list',
            'success' => $success,
            'error' => $error,
            'message' => SafeHtml::fromTrustedHtml($message),
            'rangeStartIp' => $rangeStartIp,
            'rangeEndIp' => $rangeEndIp,
            'hasRangeFilter' => $hasRangeFilter,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'actionUrl' => $actionUrl,
        ]);

    }

    public function testipPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/system/test-ip'.$suffix, 308);
    }

    public function testIpSubmit(TestIpRequest $request): View|RedirectResponse|Response
    {
        return $this->testip($request);
    }

    public function testip(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied');
        }

        if ($request->isMethod('post')) {
            $ip = (string) request()->post('ip');
        } else {
            $ip = (string) (request()->query('ip') ?? '');
        }

        $hasResult = false;
        $isBanned = false;
        $banRows = [];

        if ($ip !== '') {
            $nip = ip2long($ip);
            if ($nip === false || $nip === -1) {
                return $this->legacyAbortResponse('Error', 'Bad IP.');
            }
            $rows = $this->moderationRepository->findMatchingBans((int) $nip);
            $hasResult = true;
            $isBanned = ! empty($rows);
            foreach ($rows as $row) {
                $arr = (array) $row;
                $banRows[] = [
                    'first' => long2ip($arr['first']),
                    'last' => long2ip($arr['last']),
                    'comment' => (string) $arr['comment'],
                ];
            }
        }

        return $this->legacyPage($request, 'testip', true, [
            'ip' => $ip,
            'isBanned' => $isBanned,
            'banRows' => $banRows,
            'hasResult' => $hasResult,
        ]);

    }
}
