<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Enums\ExamType;
use App\Http\Requests\FreeleechRequest;
use App\Models\User;
use App\Repositories\UserDetailRepository;
use App\Support\AssetAppender;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\Promotion;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Js;
use Illuminate\View\View;

class BonusShopController extends LegacyController
{
    public function __construct(private readonly UserDetailRepository $userDetailRepository, private readonly ExamRepositoryInterface $examRepository,
        private readonly CurrentUser $currentUser,
        private readonly ?NexusCache $cache,
    ) {}

    public function task(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());

        $total = $this->examRepository->countEnabledTasks();
        $perPage = 20;
        [$pagertop, $pagerbottom, , $offset, $pageSize] = Pagination::pager($perPage, $total, '?');

        $examRows = $this->examRepository->listEnabledTasks($offset, $pageSize);

        $userInfo = $this->userDetailRepository->findOrFailById($currentUserId, User::$commonFields);
        $userTasks = $userInfo->onGoingExamAndTasks()
            ->where('type', ExamType::TASK->value)
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('id');

        $claimBtnText = Locale::trans('exam.action_claim_task', [], null);
        $claimedText = Locale::trans('exam.claimed_already', [], null);
        $infiniteText = Locale::trans('label.infinite', [], null);

        $rows = [];
        foreach ($examRows as $row) {
            $isClaimed = $userTasks->has($row->id);
            $btnText = $isClaimed ? $claimedText : $claimBtnText;
            $rows[] = [
                'id' => $row->id,
                'name' => $row->name,
                'indexFormatted' => $row->indexFormatted,
                'beginForUser' => $row->getBeginForUser(),
                'endForUser' => $row->getEndForUser(),
                'filterFormatted' => $row->filterFormatted,
                'rewardFormatted' => number_format((float) $row->success_reward_bonus),
                'deductFormatted' => number_format((float) $row->fail_deduct_bonus),
                'claimedCount' => ($row->on_going_users_count ?? 0).'/'.($row->max_user_count ?: $infiniteText),
                'description' => SafeHtml::fromUntrustedHtml((string) ($row->description ?? '')),
                'claimable' => ! $isClaimed,
                'claimText' => $btnText,
            ];
        }

        $title = Locale::trans('exam.type_task', [], null);
        $confirmBuyJs = Js::from(Locale::trans('exam.confirm_to_claim', [], null));

        $js = <<<JS
document.querySelectorAll('.claim').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-id')
        layer.confirm({$confirmBuyJs}, function (index) {
            layer.close(index)
            var params = {exam_id: id}
            console.log(params)
            nativePost('/web/tasks/claim', params, function(response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            })
        })
    })
})
JS;
        AssetAppender::js($js, 'footer', false);

        return $this->renderPage($request, 'task', true, [
            'title' => $title,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'columnNameLabel' => Locale::trans('label.name', [], null),
            'columnIndexLabel' => Locale::trans('exam.index', [], null),
            'columnBeginTimeLabel' => Locale::trans('label.begin', [], null),
            'columnEndTimeLabel' => Locale::trans('label.end', [], null),
            'columnTargetUserLabel' => Locale::trans('label.exam.filter_formatted', [], null),
            'columnSuccessRewardLabel' => Locale::trans('exam.success_reward_bonus', [], null),
            'columnFailDeductLabel' => Locale::trans('exam.fail_deduct_bonus', [], null),
            'columnClaimedUserCountLabel' => Locale::trans('exam.claimed_user_count', [], null),
            'columnDescLabel' => Locale::trans('label.description', [], null),
            'columnClaimLabel' => Locale::trans('exam.action_claim_task', [], null),
        ]);

    }

    public function freeleech(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        $action = trim((string) (request()->post('action') ?? request()->query('action') ?? 'main'));
        $action = htmlspecialchars($action);

        $stateMap = [
            'setallfree' => 2,
            'setall2up' => 3,
            'setall2up_free' => 4,
            'setallhalf_down' => 5,
            'setall2up_half_down' => 6,
            'setallnormal' => 1,
        ];

        if (isset($stateMap[$action])) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        $links = [
            'setallfree' => 'set all torrents free',
            'setall2up' => 'set all torrents 2x up',
            'setall2up_free' => 'set all torrents 2x up and free',
            'setallhalf_down' => 'set all torrents half down',
            'setall2up_half_down' => 'set all torrents 2x up and half down',
            'setallnormal' => 'set all torrents normal',
        ];

        $message = '';
        foreach ($links as $key => $label) {
            $message .= 'Click <a class=altlink href=/web/freeleech?action='.$key.'>here</a> to '.$label.'..<br />';
        }

        return $this->abortResponse('Select action', $message, false);

    }

    public function freeleechSubmit(FreeleechRequest $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        $action = trim((string) (request()->post('action') ?? request()->query('action') ?? 'main'));
        $action = htmlspecialchars($action);

        $stateMap = [
            'setallfree' => 2,
            'setall2up' => 3,
            'setall2up_free' => 4,
            'setallhalf_down' => 5,
            'setall2up_half_down' => 6,
            'setallnormal' => 1,
        ];

        $messages = [
            'setallfree' => 'All torrents have been set free..',
            'setall2up' => 'All torrents have been set 2x up..',
            'setall2up_free' => 'All torrents have been set 2x up and free..',
            'setallhalf_down' => 'All torrents have been set half down..',
            'setall2up_half_down' => 'All torrents have been set half down..',
            'setallnormal' => 'All torrents have been set normal..',
        ];

        if (isset($stateMap[$action])) {
            Promotion::setGlobalSpecialState($stateMap[$action]);
            $this->cache?->forget('global_promotion_state');

            return $this->abortResponse('Success', $messages[$action]);
        }

        return $this->freeleech($request);
    }
}
