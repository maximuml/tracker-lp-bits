<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HitAndRunStatus;
use App\Enums\Permission\PermissionEnum;
use App\Models\HitAndRun;
use App\Models\User;
use App\Services\BonusPageService;
use App\Services\BonusService;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
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

    private CurrentUser $currentUser;

    public function __construct(BonusPageService $bonusPageService, BonusService $bonusService, CurrentUser $currentUser)
    {
        $this->bonusPageService = $bonusPageService;
        $this->bonusService = $bonusService;
        $this->currentUser = $currentUser;
    }

    public function bonus(Request $request): View|Response|RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/mybonus.php'.($qs ? '?'.$qs : ''));
        }

        $data = $this->bonusPageService->build($request)->toArray();

        $actionRedirect = $this->bonusService->handleExchangeActionPublic(
            $request,
            $data['allBonus'],
            $data['curUser'],
            $data['lockText']
        );
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        return view('my.bonus', $data);
    }

    public function bonusExchange(Request $request): RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/mybonus.php'.($qs ? '?'.$qs : ''));
        }

        $data = $this->bonusPageService->build($request)->toArray();

        $actionRedirect = $this->bonusService->handleExchangeActionPublic(
            $request,
            $data['allBonus'],
            $data['curUser'],
            $data['lockText']
        );
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        return redirect('/mybonus.php');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        $curUser = $this->currentUser->get();
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

        $status = request()->query('status') ?? HitAndRunStatus::INSPECTING->value;
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

        $hasActionRemove = collect($list)->contains(
            fn ($row) => $row->uid == $curUser['id'] && in_array($row->status, HitAndRun::CAN_PARDON_STATUS)
        );
        if ($hasActionRemove) {
            $msg = Locale::trans('hr.remove_confirm_msg', ['bonus' => $cancelHrBonus], null);
            $js = <<<JS
document.getElementById('hr-table').addEventListener('click', function (e) {
    if (!e.target || !e.target.classList || !e.target.classList.contains('remove-hr')) return;
    var id = e.target.getAttribute('data-id');
    layer.confirm('{$msg}', function (index) {
        nativePost('ajax.php', {"action": "removeHitAndRun", "params": {"id": id}}, function (response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        })
    })
})
JS;
            AssetAppender::js($js, 'footer', false);
        }

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
            'rescount' => $rescount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'list' => $list,
            'cancelHrBonus' => $cancelHrBonus,
        ]);
    }
}
