<?php

namespace App\Http\Controllers;

use App\Models\HitAndRun;
use App\Models\User;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Enums\Permission\PermissionEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyController extends Controller
{
    public function bonus(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/mybonus.php' . ($qs ? '?' . $qs : ''));
        }

        return view('my.bonus');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/myhr.php' . ($qs ? '?' . $qs : ''));
        }

        $viewerId = (int) ($curUser['id'] ?? 0);
        $userid = $viewerId;
        $pagerParams = [];

        $requestedUserId = SupportContext::getQuery('userid');
        if (! empty($requestedUserId)) {
            if (! Permissions::userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId) && (int) $requestedUserId != $viewerId) {
                LegacyResponse::permissionDenied($viewhistory_class ?? null);
            }
            $userid = (int) $requestedUserId;
            $pagerParams['userid'] = $userid;
        }

        $userInfo = User::query()->find($userid, User::$commonFields);
        if (! $userInfo instanceof User) {
            LegacyResponse::abort('Error', 'User not exists.');
        }

        $status = SupportContext::getQuery('status') ?? HitAndRun::STATUS_INSPECTING;
        $allStatus = HitAndRun::listStatus();
        $pagerParams['status'] = $status;
        $filterParams = $pagerParams;
        $queryString = http_build_query($pagerParams);
        $headerFilters = [];
        foreach ($allStatus as $key => $value) {
            $filterParams['status'] = $key;
            $headerFilters[] = sprintf('<a href="?%s" class="%s"><b>%s</b></a>', http_build_query($filterParams), $key == $status ? 'faqlink' : '', $value['text']);
        }

        $q = htmlspecialchars((string) (SupportContext::getQuery('q') ?? ''));
        $lang_myhr = (array) SupportContext::getGlobal('lang_myhr', []);

        $baseQuery = HitAndRun::query()->where('uid', $userid)->where('status', $status);
        $rescount = (int) (clone $baseQuery)->count();
        [$pagertop, $pagerbottom, $limit, $offset, $pageSize] = Pagination::pager(50, $rescount, sprintf('?%s&', $queryString));

        $list = [];
        if ($rescount > 0) {
            $query = (clone $baseQuery)
                ->with([
                    'torrent' => function ($query) {$query->select(['id', 'size', 'name', 'category']);},
                    'torrent.basic_category',
                    'snatch',
                    'user' => function ($query) {$query->select(['id', 'lang']);},
                    'user.language',
                ])
                ->offset($offset)
                ->limit($pageSize)
                ->orderBy('id', 'desc');
            if (! empty($q)) {
                $query->where('id', $q);
            }
            $list = $query->get();
        }

        $cancelHrBonus = \App\Support\Config\SiteConfig::current()->bonus->cancelHr();

        return view('my.hr', [
            'CURUSER' => $curUser,
            'userInfo' => $userInfo,
            'userid' => $userid,
            'status' => $status,
            'allStatus' => $allStatus,
            'headerFilters' => $headerFilters,
            'queryString' => $queryString,
            'q' => $q,
            'requestUri' => SupportContext::getServerValue('REQUEST_URI'),
            'lang_myhr' => $lang_myhr,
            'rescount' => $rescount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'list' => $list,
            'cancelHrBonus' => $cancelHrBonus,
        ]);
    }
}
