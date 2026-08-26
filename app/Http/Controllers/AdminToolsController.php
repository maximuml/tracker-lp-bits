<?php

namespace App\Http\Controllers;

use App\Models\UserBanLog;
use App\Repositories\ModerationRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Html;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminToolsController extends LegacyController
{
    private ModerationRepository $moderationRepository;

    public function __construct(ModerationRepository $moderationRepository)
    {
        $this->moderationRepository = $moderationRepository;
    }

    public function userBanLog(Request $request): View|RedirectResponse|Response
    {
        if (app(CurrentUser::class)->get() === null) {
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
                $cache = app(LegacyRedisCache::class);
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
                DB::table('locations')->where('id', $delid)->delete();
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
                DB::table('locations')->where('id', $id)->update([
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
            $editRow = (array) DB::table('locations')->where('id', $editid)->first();
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
                DB::table('locations')->insert([
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

        $baseQuery = DB::table('locations')
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
