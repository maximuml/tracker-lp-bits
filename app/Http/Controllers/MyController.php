<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\HitAndRunStatus;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\ExchangeBonusRequest;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Services\BonusPageService;
use App\Services\BonusService;
use App\Services\HitAndRunPageService;
use App\Services\PermissionChecker;
use App\Support\AssetAppender;
use App\Support\Locale;
use App\Support\PageResponses;
use App\Support\Pagination;
use App\Support\RequestValues;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MyController extends Controller
{
    private BonusPageService $bonusPageService;

    private BonusService $bonusService;

    private ActorContext $actor;

    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly HitAndRunRepository $hitAndRunRepository, private readonly UserRepositoryInterface $userRepository, private readonly HitAndRunPageService $hitAndRunPageService, BonusPageService $bonusPageService, BonusService $bonusService, ActorContext $actor)
    {
        $this->bonusPageService = $bonusPageService;
        $this->bonusService = $bonusService;
        $this->actor = $actor;
    }

    public function bonus(Request $request): View|Response|RedirectResponse
    {
        if ($this->actor->isGuest()) {
            $qs = $request->getQueryString();

            return redirect('/web/mybonus'.($qs ? '?'.$qs : ''));
        }

        $data = $this->bonusPageService->build($request)->toArray();

        $actionRedirect = $this->bonusService->handleExchangeActionPublic(
            $request,
            $data['allBonus'],
            $this->actor,
            $data['lockText']
        );
        if ($actionRedirect instanceof RedirectResponse) {
            return $actionRedirect;
        }

        return view('my.bonus', $data);
    }

    public function exchangeBonus(ExchangeBonusRequest $request): RedirectResponse
    {
        if ($this->actor->isGuest()) {
            return redirect('/web/mybonus');
        }

        $data = $this->bonusPageService->build($request)->toArray();

        $response = $this->bonusService->handleExchange(
            $request,
            $data['allBonus'],
            $this->actor,
            $data['lockText']
        );

        return $response instanceof RedirectResponse ? $response : redirect('/web/mybonus');
    }

    public function hr(Request $request): View|RedirectResponse
    {
        if ($this->actor->isGuest()) {
            $qs = $request->getQueryString();

            return redirect('/web/myhr'.($qs ? '?'.$qs : ''));
        }

        $curUser = $this->actor->toLegacyArray();
        $viewerId = $this->actor->id;
        $userid = $viewerId;
        $pagerParams = [];

        $requestedUserId = $request->query('userid');
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

        $status = $request->query('status') ?? HitAndRunStatus::INSPECTING->value;
        $status = is_array($status) ? HitAndRunStatus::INSPECTING->value : $status;
        $allStatus = HitAndRun::listStatus();
        $pagerParams['status'] = $status;
        $filterParams = $pagerParams;
        $queryString = http_build_query($pagerParams);
        $headerFilters = [];
        foreach ($allStatus as $key => $value) {
            $filterParams['status'] = $key;
            $headerFilters[] = ['query' => http_build_query($filterParams), 'active' => $key == $status, 'text' => $value['text'], 'status' => (int) $key];
        }

        $q = htmlspecialchars((string) ($request->query('q') ?? ''));

        $rescount = $this->hitAndRunRepository->countForUser($userid, $status);
        [$pagertop, $pagerbottom, $limit, $offset, $pageSize] = Pagination::pager(50, $rescount, sprintf('?%s&', $queryString));

        $list = [];
        if ($rescount > 0) {
            $searchId = $q === '' ? null : (int) $q;
            $list = $this->hitAndRunRepository->paginateForUser($userid, $status, $offset, $pageSize, $searchId);
        }

        $cancelHrBonus = BonusLogs::getBonusForCancelHitAndRun();
        $page = $this->hitAndRunPageService->build($userid, $viewerId, $list);

        $hasActionRemove = collect($list)->contains(
            fn ($row) => $row->uid == $this->actor->id && in_array($row->status, HitAndRun::CAN_PARDON_STATUS)
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
            'requestUri' => RequestValues::serverValue('REQUEST_URI'),
            'rescount' => $rescount,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'list' => $list,
            'cancelHrBonus' => $cancelHrBonus,
            'page' => $page,
        ]);
    }
}
