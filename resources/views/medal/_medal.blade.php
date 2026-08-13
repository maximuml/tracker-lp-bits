<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$query = \App\Models\Medal::query()->where('display_on_medal_page', 1)
    ->orderBy('priority', 'desc')->orderBy("id", 'desc');
$q = htmlspecialchars(\App\Support\SupportContext::getRequestInput('q') ?? '');
if (!empty($q)) {
    $query->where('username', 'name', "%{$q}%");
}
$total = (clone $query)->count();
$perPage = 20;
list($paginationTop, $paginationBottom, $limit, $offset) = \App\Support\Pagination::pager($perPage, $total, "?");
$rows = (clone $query)->offset($offset)->take($perPage)->orderBy('id', 'desc')->get();
$q = htmlspecialchars($q);
$title = \App\Support\Locale::trans('medal.label', [], null);
$columnNameLabel = \App\Support\Locale::trans('label.name', [], null);
$columnImageLargeLabel = \App\Support\Locale::trans('medal.fields.image_large', [], null);
$columnPriceLabel = \App\Support\Locale::trans('medal.fields.price', [], null);
$columnDurationLabel = \App\Support\Locale::trans('medal.fields.duration', [], null);
$columnDescriptionLabel = \App\Support\Locale::trans('medal.fields.description', [], null);
$columnBuyLabel = \App\Support\Locale::trans('medal.buy_btn', [], null);
$columnSaleBeginEndTimeLabel = \App\Support\Locale::trans('medal.fields.sale_begin_end_time', [], null);
$columnInventoryLabel = \App\Support\Locale::trans('medal.fields.inventory', [], null);
$columnBonusAdditionLabel = \App\Support\Locale::trans('medal.fields.bonus_addition', [], null);
$columnGiftLabel = \App\Support\Locale::trans('medal.gift_btn', [], null);
$columnGiftFeeLabel = \App\Support\Locale::trans('medal.fields.gift_fee', [], null);
$header = '<h1 style="text-align: center">'.$title.'</h1>';
$filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$__server_REQUEST_URI}" method="get">
        <input id="q" type="text" name="q" value="{$q}" placeholder="username">
        <input type="submit">
        <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
    </form>
</div>
FORM;
\App\Support\Html::stdhead($title);
\App\Support\Frame::mainFrameOpen();
$table = <<<TABLE
<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
<td class="colhead">ID</td>
<td class="colhead">$columnImageLargeLabel</td>
<td class="colhead">$columnDescriptionLabel</td>
<td class="colhead" style="width: 115px">$columnSaleBeginEndTimeLabel</td>
<td class="colhead">$columnDurationLabel</td>
<td class="colhead">$columnBonusAdditionLabel</td>
<td class="colhead">$columnPriceLabel</td>
<td class="colhead">$columnInventoryLabel</td>
<td class="colhead">$columnBuyLabel</td>
<td class="colhead">$columnGiftLabel</td>
</tr>
</thead>
TABLE;
$now = now();
$user = \App\Models\User::query()->findOrFail($CURUSER['id']);
$table .= '<tbody>';
$userMedals = $user->valid_medals->keyBy('id');
foreach ($rows as $row) {
    $buyDisabled = $giftDisabled = ' disabled';
    $buyClass = $giftClass = '';
    try {
        $row->checkCanBeBuy();
        if ($userMedals->has($row->id)) {
            $buyBtnText = \App\Support\Locale::trans('medal.buy_already', [], null);
        } elseif ($CURUSER['seedbonus'] < $row->price) {
            $buyBtnText = \App\Support\Locale::trans('medal.require_more_bonus', [], null);
        } else {
            $buyBtnText = \App\Support\Locale::trans('medal.buy_btn', [], null);
            $buyDisabled = '';
            $buyClass = 'buy';
        }
        if ($CURUSER['seedbonus'] < $row->price * (1 + ($row->gift_fee_factor ?? 0))) {
            $giftBtnText = \App\Support\Locale::trans('medal.require_more_bonus', [], null);
        } else {
            $giftBtnText = \App\Support\Locale::trans('medal.gift_btn', [], null);
            $giftDisabled = '';
            $giftClass = 'gift';
        }
    } catch (\Exception $exception) {
        $buyBtnText = $giftBtnText = $exception->getMessage();
    }
    $buyAction = sprintf(
        '<input type="button" class="%s" data-id="%s" value="%s"%s>',
        $buyClass, $row->id, $buyBtnText, $buyDisabled
    );
    $giftAction = sprintf(
        '<input type="number" class="uid" %s style="width: 60px" placeholder="UID"><input type="button" class="%s" data-id="%s" value="%s"%s><span class="nowrap">%s: %s</span>',
         $giftDisabled, $giftClass, $row->id, $giftBtnText, $giftDisabled, $columnGiftFeeLabel, (($row->gift_fee_factor ?? 0) * 100).'%'
    );
    $table .= sprintf(
        '<tr><td>%s</td><td><img src="%s" style="max-width: 60px;max-height: 60px;" class="preview" /></td><td><h1>%s</h1>%s</td><td>%s ~<br>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
        $row->id,$row->image_large, $row->name, $row->description, $row->sale_begin_time ?? \App\Support\Locale::trans('nexus.no_limit', [], null), $row->sale_end_time ?? \App\Support\Locale::trans('nexus.no_limit', [], null), $row->durationText, (($row->bonus_addition_factor ?? 0) * 100).'%', number_format($row->price),  $row->inventory ?? \App\Support\Locale::trans('label.infinite', [], null), $buyAction, $giftAction
    );
}
$table .= '</tbody></table>';
echo $header . $table . $paginationBottom;
\App\Support\Frame::mainFrameClose();
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
\App\Support\Html::stdfoot();

