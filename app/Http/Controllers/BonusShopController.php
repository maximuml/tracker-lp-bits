<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Medal;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Nexus\Nexus;

class BonusShopController extends LegacyController
{
    public function medal(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $seedbonus = (float) ($curUser['seedbonus'] ?? 0);

        $q = htmlspecialchars(trim((string) (SupportContext::getRequestInput('q') ?? '')));

        $query = Medal::query()
            ->where('display_on_medal_page', 1)
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc');

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $total = (clone $query)->count();
        $perPage = 20;
        [$pagertop, $pagerbottom, , $offset, $pageSize] = Pagination::pager($perPage, $total, '?');
        $medalRows = (clone $query)->offset($offset)->take($pageSize)->orderBy('id', 'desc')->get();

        $user = User::query()->findOrFail($currentUserId);
        $userMedals = $user->valid_medals->keyBy('id');

        $rows = [];
        foreach ($medalRows as $row) {
            $buyDisabled = $giftDisabled = ' disabled';
            $buyClass = $giftClass = '';
            $buyBtnText = $giftBtnText = '';
            try {
                $row->checkCanBeBuy();
                if ($userMedals->has($row->id)) {
                    $buyBtnText = Locale::trans('medal.buy_already', [], null);
                } elseif ($seedbonus < $row->price) {
                    $buyBtnText = Locale::trans('medal.require_more_bonus', [], null);
                } else {
                    $buyBtnText = Locale::trans('medal.buy_btn', [], null);
                    $buyDisabled = '';
                    $buyClass = 'buy';
                }

                $giftCost = $row->price * (1 + ($row->gift_fee_factor ?? 0));
                if ($seedbonus < $giftCost) {
                    $giftBtnText = Locale::trans('medal.require_more_bonus', [], null);
                } else {
                    $giftBtnText = Locale::trans('medal.gift_btn', [], null);
                    $giftDisabled = '';
                    $giftClass = 'gift';
                }
            } catch (\Exception $exception) {
                $buyBtnText = $giftBtnText = $exception->getMessage();
            }

            $rows[] = [
                'id' => $row->id,
                'image_large' => $row->image_large,
                'name' => $row->name,
                'description' => $row->description,
                'sale_begin_time' => $row->sale_begin_time ?? Locale::trans('nexus.no_limit', [], null),
                'sale_end_time' => $row->sale_end_time ?? Locale::trans('nexus.no_limit', [], null),
                'durationText' => $row->durationText,
                'bonus_addition_factor' => ($row->bonus_addition_factor ?? 0) * 100,
                'gift_fee_factor' => ($row->gift_fee_factor ?? 0) * 100,
                'price' => $row->price,
                'inventory' => $row->inventory ?? Locale::trans('label.infinite', [], null),
                'buy_action' => sprintf('<input type="button" class="%s" data-id="%s" value="%s"%s>', $buyClass, $row->id, htmlspecialchars($buyBtnText), $buyDisabled),
                'gift_action' => sprintf('<input type="number" class="uid" %s style="width: 60px" placeholder="UID"><input type="button" class="%s" data-id="%s" value="%s"%s><span class="nowrap">%s: %s</span>', $giftDisabled, $giftClass, $row->id, htmlspecialchars($giftBtnText), $giftDisabled, Locale::trans('medal.fields.gift_fee', [], null), (($row->gift_fee_factor ?? 0) * 100).'%'),
            ];
        }

        $title = Locale::trans('medal.label', [], null);
        $confirmBuyMsg = Locale::trans('medal.confirm_to_buy', [], null);
        $confirmGiftMsg = Locale::trans('medal.confirm_to_gift', [], null);

        $js = <<<JS
jQuery('.buy').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        let params = {
            action: "buyMedal",
            params: {medal_id: medalId}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
jQuery('.gift').on('click', function (e) {
    let medalId = jQuery(this).attr('data-id')
    let uid = jQuery(this).prev().val()
    if (!uid) {
        layer.alert('Require UID')
        return
    }
    layer.confirm("{$confirmGiftMsg}" + uid + " ?", function (index) {
        let params = {
            action: "giftMedal",
            params: {medal_id: medalId, uid: uid}
        }
        console.log(params)
        jQuery.post('ajax.php', params, function(response) {
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
JS;
        Nexus::js($js, 'footer', false);

        return $this->legacyPage($request, 'medal', true, [
            'title' => $title,
            'q' => $q,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'columnImageLargeLabel' => Locale::trans('medal.fields.image_large', [], null),
            'columnDescriptionLabel' => Locale::trans('medal.fields.description', [], null),
            'columnSaleBeginEndTimeLabel' => Locale::trans('medal.fields.sale_begin_end_time', [], null),
            'columnDurationLabel' => Locale::trans('medal.fields.duration', [], null),
            'columnBonusAdditionLabel' => Locale::trans('medal.fields.bonus_addition', [], null),
            'columnPriceLabel' => Locale::trans('medal.fields.price', [], null),
            'columnInventoryLabel' => Locale::trans('medal.fields.inventory', [], null),
            'columnBuyLabel' => Locale::trans('medal.buy_btn', [], null),
            'columnGiftLabel' => Locale::trans('medal.gift_btn', [], null),
            'columnGiftFeeLabel' => Locale::trans('medal.fields.gift_fee', [], null),
        ]);

    }

    public function task(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        $query = Exam::query()
            ->where('type', Exam::TYPE_TASK)
            ->where('status', Exam::STATUS_ENABLED);

        $total = (clone $query)->count();
        $perPage = 20;
        [$pagertop, $pagerbottom, , $offset, $pageSize] = Pagination::pager($perPage, $total, '?');

        $examRows = (clone $query)
            ->offset($offset)
            ->take($pageSize)
            ->orderBy('id', 'desc')
            ->withCount('onGoingUsers')
            ->get();

        $userInfo = User::query()->findOrFail($currentUserId, User::$commonFields);
        $userTasks = $userInfo->onGoingExamAndTasks()
            ->where('type', Exam::TYPE_TASK)
            ->orderBy('id', 'desc')
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($examRows as $row) {
            $isClaimed = $userTasks->has($row->id);
            $rows[] = [
                'id' => $row->id,
                'name' => $row->name,
                'indexFormatted' => $row->indexFormatted,
                'beginForUser' => $row->getBeginForUser(),
                'endForUser' => $row->getEndForUser(),
                'filterFormatted' => $row->filterFormatted,
                'success_reward_bonus' => $row->success_reward_bonus,
                'fail_deduct_bonus' => $row->fail_deduct_bonus,
                'on_going_users_count' => $row->on_going_users_count ?? 0,
                'max_user_count' => $row->max_user_count,
                'description' => $row->description,
                'claimed' => $isClaimed,
            ];
        }

        $title = Locale::trans('exam.type_task', [], null);
        $confirmBuyMsg = Locale::trans('exam.confirm_to_claim', [], null);
        $confirmGiftMsg = Locale::trans('medal.confirm_to_gift', [], null);

        $js = <<<JS
jQuery('.claim').on('click', function (e) {
    let id = jQuery(this).attr('data-id')
    layer.confirm("{$confirmBuyMsg}", function (index) {
        layer.close(index)
        let params = {
            action: "claimTask",
            params: {exam_id: id}
        }
        console.log(params)
        jQuery('body').loading({
            stoppable: false
        });
        jQuery.post('ajax.php', params, function(response) {
            jQuery('body').loading('stop');
            console.log(response)
            if (response.ret != 0) {
                layer.alert(response.msg)
                return
            }
            window.location.reload()
        }, 'json')
    })
})
JS;
        Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
        Nexus::js($js, 'footer', false);

        return $this->legacyPage($request, 'task', true, [
            'title' => $title,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'claimBtnText' => Locale::trans('exam.action_claim_task', [], null),
            'claimedText' => Locale::trans('exam.claimed_already', [], null),
            'infiniteText' => Locale::trans('label.infinite', [], null),
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
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        $action = trim((string) (SupportContext::getPost('action') ?? SupportContext::getQuery('action') ?? 'main'));
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
            DB::table('torrents_state')->update(['global_sp_state' => $stateMap[$action]]);
            app(LegacyRedisCache::class)?->delete_value('global_promotion_state');

            return $this->legacyAbortResponse('Success', $messages[$action]);
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
            $message .= 'Click <a class=altlink href=freeleech.php?action='.$key.'>here</a> to '.$label.'..<br />';
        }

        return $this->legacyAbortResponse('Select action', $message, false);

    }
}
