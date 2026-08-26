<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\HitAndRun;
use App\Models\User;
use App\Services\BonusPageService;
use App\Services\Legacy\BonusService;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyController extends Controller
{
    private BonusPageService $bonusPageService;

    private BonusService $bonusService;

    public function __construct(BonusPageService $bonusPageService, BonusService $bonusService)
    {
        $this->bonusPageService = $bonusPageService;
        $this->bonusService = $bonusService;
    }

    public function bonus(Request $request): View|Response|RedirectResponse
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/mybonus.php'.($qs ? '?'.$qs : ''));
        }

        $data = $this->bonusPageService->build($request);

        $actionRedirect = $this->bonusService->handleExchangeActionPublic(
            $request,
            $data['allBonus'],
            $data['curUser'],
            $data['lang'],
            $data['lockText']
        );
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        return view('my.bonus', $data);
    }

    public function hr(Request $request): View|RedirectResponse
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/myhr.php'.($qs ? '?'.$qs : ''));
        }

        $viewerId = (int) ($curUser['id'] ?? 0);
        $userid = $viewerId;
        $pagerParams = [];

        $requestedUserId = request()->query('userid');
        if (! empty($requestedUserId)) {
            if (! Permissions::userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId) && (int) $requestedUserId != $viewerId) {
                LegacyResponse::permissionDenied();
            }
            $userid = (int) $requestedUserId;
            $pagerParams['userid'] = $userid;
        }

        $userInfo = User::query()->find($userid, User::$commonFields);
        if (! $userInfo instanceof User) {
            LegacyResponse::abort('Error', 'User not exists.');
        }

        $status = request()->query('status') ?? HitAndRun::STATUS_INSPECTING;
        $allStatus = HitAndRun::listStatus();
        $pagerParams['status'] = $status;
        $filterParams = $pagerParams;
        $queryString = http_build_query($pagerParams);
        $headerFilters = [];
        foreach ($allStatus as $key => $value) {
            $filterParams['status'] = $key;
            $headerFilters[] = sprintf('<a href="?%s" class="%s"><b>%s</b></a>', http_build_query($filterParams), $key == $status ? 'faqlink' : '', $value['text']);
        }

        $q = htmlspecialchars((string) (request()->query('q') ?? ''));
        $lang_myhr = (array) app(Globals::class)->get('lang_myhr', []);

        $baseQuery = HitAndRun::query()->where('uid', $userid)->where('status', $status);
        $rescount = (int) (clone $baseQuery)->count();
        [$pagertop, $pagerbottom, $limit, $offset, $pageSize] = Pagination::pager(50, $rescount, sprintf('?%s&', $queryString));

        $list = [];
        if ($rescount > 0) {
            $query = (clone $baseQuery)
                ->with([
                    'torrent' => function ($query) {
                        $query->select(['id', 'size', 'name', 'category']);
                    },
                    'torrent.basic_category',
                    'snatch',
                    'user' => function ($query) {
                        $query->select(['id', 'lang']);
                    },
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

        $cancelHrBonus = SiteConfig::current()->bonus->cancelHr();

        return view('my.hr', [
            'CURUSER' => $curUser,
            'userInfo' => $userInfo,
            'userid' => $userid,
            'status' => $status,
            'allStatus' => $allStatus,
            'headerFilters' => $headerFilters,
            'queryString' => $queryString,
            'q' => $q,
            'requestUri' => Input::serverValue('REQUEST_URI'),
            'lang_myhr' => $lang_myhr,
            'rescount' => $rescount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'list' => $list,
            'cancelHrBonus' => $cancelHrBonus,
        ]);
    }
}
