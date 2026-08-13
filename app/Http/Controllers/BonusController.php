<?php

namespace App\Http\Controllers;

use App\Models\BonusLogs;
use App\Models\Exam;
use App\Models\Medal;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Api;
use App\Support\Bonus;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class BonusController extends LegacyController
{
    public function bonusLog(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $uid = (int) (SupportContext::getRequestInput('uid') ?? $curUser['id'] ?? 0);

        if (! \App\Support\Validators::isId($uid)) {
            return $this->legacyAbortResponse('Error', 'Invalid uid.');
        }

        $user = User::query()->where('id', $uid)->first(User::$commonFields);
        if (! $user) {
            return $this->legacyAbortResponse('Error', "Invalid uid: {$uid}");
        }

        if ($uid != ($curUser['id'] ?? 0)) {
            $allowed = \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY, $user);
            if (! $allowed) {
                return $this->legacyAbortResponse('Error', 'Permission denied.');
            }
        }

        $isRecordSeedingBonusLog = Setting::getIsRecordSeedingBonusLog();
        $defaultCategory = BonusLogs::CATEGORY_COMMON;
        $category = SupportContext::getRequestInput('category') ?? $defaultCategory;
        $categoryOptions = BonusLogs::listCategoryOptions($isRecordSeedingBonusLog);
        if (! isset($categoryOptions[$category])) {
            return $this->legacyAbortResponse('Error', "Invalid category: {$category}");
        }

        $businessType = (int) (SupportContext::getRequestInput('business_type') ?? 0);
        $businessTypeOptions = BonusLogs::listBusinessTypeOptions($isRecordSeedingBonusLog ? '' : $defaultCategory);
        if ($businessType && ! isset($businessTypeOptions[$businessType])) {
            return $this->legacyAbortResponse('Error', "Invalid business_type: {$businessType}");
        }

        $title = \App\Support\Locale::trans('bonus-log.title_for_user', [], null);
        $pagerParam = "?uid={$uid}&category={$category}&business_type={$businessType}";
        $textSelectOnePlease = \App\Support\Locale::trans('nexus.select_one_please', [], null);
        $resetText = \App\Support\Locale::trans('label.reset', [], null);
        $submitText = \App\Support\Locale::trans('label.submit', [], null);
        $categoryText = \App\Support\Locale::trans('bonus-log.category', [], null);
        $businessTypeText = \App\Support\Locale::trans('bonus-log.fields.business_type', [], null);

        $categoryOptionsHtml = '';
        foreach ($categoryOptions as $name => $text) {
            $selected = (SupportContext::getRequestInput('category') ?? '') == $name ? ' selected' : '';
            $categoryOptionsHtml .= sprintf('<option value="%s"%s>%s</option>', htmlspecialchars((string) $name), $selected, htmlspecialchars($text));
        }

        $businessTypeOptionsHtml = '';
        foreach ($businessTypeOptions as $name => $text) {
            $selected = (SupportContext::getRequestInput('business_type') ?? '') == $name ? ' selected' : '';
            $businessTypeOptionsHtml .= sprintf('<option value="%s"%s>%s</option>', htmlspecialchars((string) $name), $selected, htmlspecialchars($text));
        }

        $rep = new \App\Repositories\BonusRepository();
        $total = $rep->getCount($category, $uid, $businessType);
        [$pagertop, $pagerbottom, , , $pageSize, $page] = \App\Support\Pagination::pager(50, $total, "{$pagerParam}&");
        $list = $rep->getList($category, $uid, $businessType, $page + 1, $pageSize);

        $rows = [];
        foreach ($list as $row) {
            $r = (array) $row;
            $old = (float) ($r['old_total_value'] ?? 0);
            $new = (float) ($r['new_total_value'] ?? 0);
            $value = (float) ($r['value'] ?? 0);
            $rows[] = [
                'businessTypeText' => $r['businessTypeText'] ?? '',
                'old_formatted' => $old > 0 ? number_format($old, 1) : '-',
                'value_formatted' => ($old < $new ? '+' : '-') . number_format($value, 1),
                'new_formatted' => $new > 0 ? number_format($new, 1) : '-',
                'comment' => $r['comment'] ?? '',
                'created_at' => $r['created_at'] ?? '',
            ];
        }

        $resetJs = <<<'JS'
jQuery("#reset").on('click', function () {
    jQuery("select[name=category]").val('')
    jQuery("select[name=business_type]").val('')
})
JS;
        \Nexus\Nexus::js($resetJs, 'footer', false);

        return $this->legacyPage($request, 'bonus-log', true, [
            'title' => $title,
            'uid' => $uid,
            'username' => $user->username,
            'category' => $category,
            'businessType' => $businessType,
            'categoryText' => $categoryText,
            'businessTypeText' => $businessTypeText,
            'textSelectOnePlease' => $textSelectOnePlease,
            'resetText' => $resetText,
            'submitText' => $submitText,
            'categoryOptionsHtml' => $categoryOptionsHtml,
            'businessTypeOptionsHtml' => $businessTypeOptionsHtml,
            'pagerParam' => $pagerParam,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

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
        [$pagertop, $pagerbottom, , $offset, $pageSize] = \App\Support\Pagination::pager($perPage, $total, '?');
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
                    $buyBtnText = \App\Support\Locale::trans('medal.buy_already', [], null);
                } elseif ($seedbonus < $row->price) {
                    $buyBtnText = \App\Support\Locale::trans('medal.require_more_bonus', [], null);
                } else {
                    $buyBtnText = \App\Support\Locale::trans('medal.buy_btn', [], null);
                    $buyDisabled = '';
                    $buyClass = 'buy';
                }

                $giftCost = $row->price * (1 + ($row->gift_fee_factor ?? 0));
                if ($seedbonus < $giftCost) {
                    $giftBtnText = \App\Support\Locale::trans('medal.require_more_bonus', [], null);
                } else {
                    $giftBtnText = \App\Support\Locale::trans('medal.gift_btn', [], null);
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
                'sale_begin_time' => $row->sale_begin_time ?? \App\Support\Locale::trans('nexus.no_limit', [], null),
                'sale_end_time' => $row->sale_end_time ?? \App\Support\Locale::trans('nexus.no_limit', [], null),
                'durationText' => $row->durationText,
                'bonus_addition_factor' => ($row->bonus_addition_factor ?? 0) * 100,
                'gift_fee_factor' => ($row->gift_fee_factor ?? 0) * 100,
                'price' => $row->price,
                'inventory' => $row->inventory ?? \App\Support\Locale::trans('label.infinite', [], null),
                'buy_action' => sprintf('<input type="button" class="%s" data-id="%s" value="%s"%s>', $buyClass, $row->id, htmlspecialchars($buyBtnText), $buyDisabled),
                'gift_action' => sprintf('<input type="number" class="uid" %s style="width: 60px" placeholder="UID"><input type="button" class="%s" data-id="%s" value="%s"%s><span class="nowrap">%s: %s</span>', $giftDisabled, $giftClass, $row->id, htmlspecialchars($giftBtnText), $giftDisabled, \App\Support\Locale::trans('medal.fields.gift_fee', [], null), (($row->gift_fee_factor ?? 0) * 100) . '%'),
            ];
        }

        $title = \App\Support\Locale::trans('medal.label', [], null);
        $confirmBuyMsg = \App\Support\Locale::trans('medal.confirm_to_buy', [], null);
        $confirmGiftMsg = \App\Support\Locale::trans('medal.confirm_to_gift', [], null);

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
        \Nexus\Nexus::js($js, 'footer', false);

        return $this->legacyPage($request, 'medal', true, [
            'title' => $title,
            'q' => $q,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'columnImageLargeLabel' => \App\Support\Locale::trans('medal.fields.image_large', [], null),
            'columnDescriptionLabel' => \App\Support\Locale::trans('medal.fields.description', [], null),
            'columnSaleBeginEndTimeLabel' => \App\Support\Locale::trans('medal.fields.sale_begin_end_time', [], null),
            'columnDurationLabel' => \App\Support\Locale::trans('medal.fields.duration', [], null),
            'columnBonusAdditionLabel' => \App\Support\Locale::trans('medal.fields.bonus_addition', [], null),
            'columnPriceLabel' => \App\Support\Locale::trans('medal.fields.price', [], null),
            'columnInventoryLabel' => \App\Support\Locale::trans('medal.fields.inventory', [], null),
            'columnBuyLabel' => \App\Support\Locale::trans('medal.buy_btn', [], null),
            'columnGiftLabel' => \App\Support\Locale::trans('medal.gift_btn', [], null),
            'columnGiftFeeLabel' => \App\Support\Locale::trans('medal.fields.gift_fee', [], null),
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
        [$pagertop, $pagerbottom, , $offset, $pageSize] = \App\Support\Pagination::pager($perPage, $total, '?');

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

        $title = \App\Support\Locale::trans('exam.type_task', [], null);
        $confirmBuyMsg = \App\Support\Locale::trans('exam.confirm_to_claim', [], null);
        $confirmGiftMsg = \App\Support\Locale::trans('medal.confirm_to_gift', [], null);

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
        \Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
        \Nexus\Nexus::js($js, 'footer', false);

        return $this->legacyPage($request, 'task', true, [
            'title' => $title,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'claimBtnText' => \App\Support\Locale::trans('exam.action_claim_task', [], null),
            'claimedText' => \App\Support\Locale::trans('exam.claimed_already', [], null),
            'infiniteText' => \App\Support\Locale::trans('label.infinite', [], null),
            'columnNameLabel' => \App\Support\Locale::trans('label.name', [], null),
            'columnIndexLabel' => \App\Support\Locale::trans('exam.index', [], null),
            'columnBeginTimeLabel' => \App\Support\Locale::trans('label.begin', [], null),
            'columnEndTimeLabel' => \App\Support\Locale::trans('label.end', [], null),
            'columnTargetUserLabel' => \App\Support\Locale::trans('label.exam.filter_formatted', [], null),
            'columnSuccessRewardLabel' => \App\Support\Locale::trans('exam.success_reward_bonus', [], null),
            'columnFailDeductLabel' => \App\Support\Locale::trans('exam.fail_deduct_bonus', [], null),
            'columnClaimedUserCountLabel' => \App\Support\Locale::trans('exam.claimed_user_count', [], null),
            'columnDescLabel' => \App\Support\Locale::trans('label.description', [], null),
            'columnClaimLabel' => \App\Support\Locale::trans('exam.action_claim_task', [], null),
        ]);

    }

    public function uploaders(Request $request): View|RedirectResponse|Response
    {
        $uploaderClass = defined('UC_UPLOADER') ? \constant('UC_UPLOADER') : PHP_INT_MAX;
        if (UserDisplay::currentClass() < $uploaderClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langUploaders = (array) SupportContext::getGlobal('lang_uploaders', []);

        $year = (int) (SupportContext::getQuery('year') ?? 0);
        if (! $year || $year < 2000) {
            $year = (int) date('Y');
        }
        $month = (int) (SupportContext::getQuery('month') ?? 0);
        if (! $month || $month <= 0 || $month > 12) {
            $month = (int) date('m');
        }
        $order = (string) (SupportContext::getQuery('order') ?? '');
        if (! in_array($order, ['username', 'torrent_size', 'torrent_count'])) {
            $order = 'username';
        }

        $sortColumn = match ($order) {
            'torrent_size' => NexusDB::raw('SUM(torrents.size)'),
            'torrent_count' => NexusDB::raw('COUNT(torrents.id)'),
            default => 'users.username',
        };
        $sortDirection = $order === 'username' ? 'ASC' : 'DESC';

        $dateFounded = (string) SupportContext::getGlobal('datefounded', '2010-08-19');
        $yearFounded = (int) substr($dateFounded, 0, 4);
        if (! $yearFounded) {
            $yearFounded = 2007;
        }
        $yearNow = (int) date('Y');

        $timeStart = strtotime("{$year}-{$month}-01 00:00:00");
        $sqlStartTime = date('Y-m-d H:i:s', $timeStart);
        $timeEnd = strtotime('+1 month', $timeStart);
        $sqlEndTime = date('Y-m-d H:i:s', $timeEnd);

        $uploaders = NexusDB::table('torrents')
            ->leftJoin('users', 'torrents.owner', '=', 'users.id')
            ->where('users.class', '>=', $uploaderClass)
            ->where('torrents.added', '>', $sqlStartTime)
            ->where('torrents.added', '<', $sqlEndTime)
            ->groupBy('users.id', 'users.username')
            ->orderBy($sortColumn, $sortDirection)
            ->get([
                'users.id AS userid',
                'users.username AS username',
                NexusDB::raw('COUNT(torrents.id) AS torrent_count'),
                NexusDB::raw('SUM(torrents.size) AS torrent_size'),
            ]);

        $hasUpUserIds = [];
        $rows = [];
        foreach ($uploaders as $uploader) {
            $row = (array) $uploader;
            $lastTorrent = NexusDB::table('torrents')
                ->where('owner', (int) $row['userid'])
                ->orderByDesc('id')
                ->first(['id', 'name', 'added']);
            $last = $lastTorrent ? (array) $lastTorrent : [];
            $rows[] = [
                'userid' => (int) $row['userid'],
                'username' => $row['username'],
                'torrent_size' => (float) ($row['torrent_size'] ?? 0),
                'torrent_count' => (int) ($row['torrent_count'] ?? 0),
                'last_added' => $last['added'] ?? '',
                'last_id' => (int) ($last['id'] ?? 0),
                'last_name' => $last['name'] ?? '',
            ];
            $hasUpUserIds[] = (int) $row['userid'];
        }

        $nonUploaderQuery = User::query()
            ->where('class', '>=', $uploaderClass)
            ->when(! empty($hasUpUserIds), function ($q) use ($hasUpUserIds) {
                $q->whereNotIn('id', $hasUpUserIds);
            })
            ->orderBy('username')
            ->get(['id AS userid', 'username']);

        foreach ($nonUploaderQuery as $nonUploader) {
            $row = (array) $nonUploader->getAttributes();
            $lastTorrent = NexusDB::table('torrents')
                ->where('owner', (int) $row['userid'])
                ->orderByDesc('id')
                ->first(['id', 'name', 'added']);
            $last = $lastTorrent ? (array) $lastTorrent : [];
            $rows[] = [
                'userid' => (int) $row['userid'],
                'username' => $row['username'],
                'torrent_size' => 0,
                'torrent_count' => 0,
                'last_added' => $last['added'] ?? '',
                'last_id' => (int) ($last['id'] ?? 0),
                'last_name' => $last['name'] ?? '',
            ];
        }

        $yearOptions = '';
        for ($i = $yearFounded; $i <= $yearNow; $i++) {
            $selected = $i == $year ? ' selected="selected"' : '';
            $yearOptions .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }
        $monthOptions = '';
        for ($i = 1; $i <= 12; $i++) {
            $selected = $i == $month ? ' selected="selected"' : '';
            $monthOptions .= '<option value="' . $i . '"' . $selected . '>' . $i . '</option>';
        }

        return $this->legacyPage($request, 'uploaders', true, [
            'lang_uploaders' => $langUploaders,
            'year' => $year,
            'month' => $month,
            'order' => $order,
            'yearOptions' => $yearOptions,
            'monthOptions' => $monthOptions,
            'datefounded' => $dateFounded,
            'timeStart' => $timeStart,
            'rows' => $rows,
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
            NexusDB::table('torrents_state')->update(['global_sp_state' => $stateMap[$action]]);
            SupportContext::getCache()?->delete_value('global_promotion_state');
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
            $message .= 'Click <a class=altlink href=freeleech.php?action=' . $key . '>here</a> to ' . $label . '..<br />';
        }

        return $this->legacyAbortResponse('Select action', $message, false);

    }

    public function magic(Request $request): JsonResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $userId = (int) ($curUser['id'] ?? 0);
        $torrentId = (int) ($request->input('id') ?? 0);
        $value = (int) abs((float) ($request->input('value') ?? 0));

        if (! in_array($value, Setting::getBonusRewardOptions())) {
            return response()->json(Api::failWithContext('Invalid value.', $request->all()));
        }
        if ($value > (float) ($curUser['seedbonus'] ?? 0)) {
            return response()->json(Api::failWithContext('You do not have such bonus!', $request->all()));
        }

        $torrentOwner = Torrent::query()->where('id', $torrentId)->value('owner');
        if (! $torrentOwner) {
            return response()->json(Api::failWithContext('Invalid torrent id!', $request->all()));
        }
        if ((int) $torrentOwner === $userId) {
            return response()->json(Api::failWithContext('You are giving magic to yourself.', $request->all()));
        }

        $alreadyMagic = NexusDB::table('magic')->where('torrentid', $torrentId)->where('userid', $userId)->count();
        if ($alreadyMagic != 0) {
            return response()->json(Api::failWithContext('You already gave the magic value!', $request->all()));
        }

        $todayStr = now()->startOfDay();
        $todayCount = Reward::query()->where('userid', $userId)->where('created_at', '>=', $todayStr)->count();
        $timesLimit = Setting::getBonusRewardTimesLimit();
        if ($timesLimit > 0 && $todayCount >= $timesLimit) {
            return response()->json(Api::failWithContext('You already reach times limit!', $request->all()));
        }

        $torrentOwnerInfo = User::query()->find($torrentOwner, User::$commonFields);
        if (! $torrentOwnerInfo) {
            return response()->json(Api::failWithContext('Invalid torrent owner!', $request->all()));
        }

        NexusDB::table('magic')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
        ]);

        Bonus::updatePoints('-', (float) $value, $userId);
        BonusLogs::add($userId, (float) ($curUser['seedbonus'] ?? 0), $value, (float) ($curUser['seedbonus'] ?? 0) - $value, '', BonusLogs::BUSINESS_TYPE_REWARD_TORRENT);

        Bonus::updatePoints('+', (float) $value, (int) $torrentOwner);
        BonusLogs::add((int) $torrentOwnerInfo['id'], (float) $torrentOwnerInfo['seedbonus'], $value, (float) $torrentOwnerInfo['seedbonus'] + $value, '', BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD);

        return response()->json(Api::successWithContext('OK', $request->all()));

    }

}