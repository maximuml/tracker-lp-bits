<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\HitAndRunStatus;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\ExchangeBonusRequest;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Services\BonusPageService;
use App\Services\BonusService;
use App\Services\PermissionChecker;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Input;
use App\Support\Locale;
use App\Support\PageResponses;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyController extends Controller
{
    private BonusPageService $bonusPageService;

    private BonusService $bonusService;

    private CurrentUser $currentUser;

    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly HitAndRunRepository $hitAndRunRepository, private readonly UserRepositoryInterface $userRepository, BonusPageService $bonusPageService, BonusService $bonusService, CurrentUser $currentUser)
    {
        $this->bonusPageService = $bonusPageService;
        $this->bonusService = $bonusService;
        $this->currentUser = $currentUser;
    }

    public function bonus(Request $request): View|Response|RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/mybonus'.($qs ? '?'.$qs : ''));
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

            return redirect('/web/mybonus'.($qs ? '?'.$qs : ''));
        }

        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';
        if ($request->query('action') === 'exchange') {
            return redirect()->to('/web/mybonus/exchange'.$suffix, 308);
        }

        return redirect('/web/mybonus');
    }

    public function exchangeBonus(ExchangeBonusRequest $request): RedirectResponse
    {
        if ($this->currentUser->get() === null) {
            return redirect('/web/mybonus');
        }

        $data = $this->bonusPageService->build($request)->toArray();

        $response = $this->bonusService->handleExchange(
            $request,
            $data['allBonus'],
            $data['curUser'],
            $data['lockText']
        );

        return $response instanceof RedirectResponse ? $response : redirect('/web/mybonus');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/web/myhr'.($qs ? '?'.$qs : ''));
        }

        $viewerId = (int) ($this->currentUser->id());
        $userid = $viewerId;
        $pagerParams = [];

        $requestedUserId = request()->query('userid');
        if (! empty($requestedUserId)) {
            if (! $this->permissionChecker->userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId) && (int) $requestedUserId != $viewerId) {
                PageResponses::permissionDenied();
            }
            $userid = (int) $requestedUserId;
            $pagerParams['userid'] = $userid;
        }

        $userInfo = $this->userRepository->findById($userid, User::$commonFields);
        if (! $userInfo instanceof User) {
            PageResponses::abort('Error', 'User not exists.');
        }

        $status = request()->query('status') ?? HitAndRunStatus::INSPECTING->value;
        $status = is_array($status) ? HitAndRunStatus::INSPECTING->value : $status;
        $allStatus = HitAndRun::listStatus();
        $pagerParams['status'] = $status;
        $filterParams = $pagerParams;
        $queryString = http_build_query($pagerParams);
        $headerFilters = [];
        foreach ($allStatus as $key => $value) {
            $filterParams['status'] = $key;
            $headerFilters[] = ['query' => http_build_query($filterParams), 'active' => $key == $status, 'text' => $value['text']];
        }

        $q = htmlspecialchars((string) (request()->query('q') ?? ''));

        $rescount = $this->hitAndRunRepository->countForUser($userid, $status);
        [$pagertop, $pagerbottom, $limit, $offset, $pageSize] = Pagination::pager(50, $rescount, sprintf('?%s&', $queryString));

        $list = [];
        if ($rescount > 0) {
            $searchId = $q === '' ? null : (int) $q;
            $list = $this->hitAndRunRepository->paginateForUser($userid, $status, $offset, $pageSize, $searchId);
        }

        $cancelHrBonus = SiteConfig::current()->bonus->cancelHr();

        $hasActionRemove = collect($list)->contains(
            fn ($row) => $row->uid == $this->currentUser->id() && in_array($row->status, HitAndRun::CAN_PARDON_STATUS)
        );
        if ($hasActionRemove) {
            $msg = Locale::trans('hr.remove_confirm_msg', ['bonus' => $cancelHrBonus], null);
            $js = <<<JS
document.getElementById('hr-table').addEventListener('click', function (e) {
    if (!e.target || !e.target.classList || !e.target.classList.contains('remove-hr')) return;
    var id = e.target.getAttribute('data-id');
    layer.confirm('{$msg}', function (index) {
        nativePost('/web/hit-and-runs/remove', {"id": id}, function (response) {
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
