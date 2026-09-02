<?php

$__server_REQUEST_URI = (string) ($requestUri ?? \App\Support\Input::serverValue('REQUEST_URI'));
$CURUSER = (array) ($CURUSER ?? \app(\App\Support\CurrentUser::class)->get() ?? []);
$userInfo = $userInfo ?? null;
$userid = (int) ($userid ?? $CURUSER['id'] ?? 0);
$status = $status ?? \App\Enums\HitAndRunStatus::INSPECTING->value;
$headerFilters = (array) ($headerFilters ?? []);
$q = (string) ($q ?? '');
$lang_myhr = (array) ($lang_myhr ?? \app(\App\Support\Globals::class)->get('lang_myhr', []));
$lang_functions = (array) \app(\App\Support\Globals::class)->get('lang_functions', []);
$rescount = (int) ($rescount ?? 0);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$list = $list ?? collect();
$cancelHrBonus = (float) ($cancelHrBonus ?? 0);

if (! $userInfo instanceof \App\Models\User) {
    \App\Support\LegacyResponse::abort('Error', 'User not exists.');
}

$pageTitle = $userInfo->username . ' - H&R';
print("<h1>$pageTitle</h1>");

print("<p>" . implode(' | ', $headerFilters) . "</p>");

$filterForm = <<<FORM
<form id="filterForm" action="{$__server_REQUEST_URI}" method="get">
    <input id="q" type="text" name="q" value="{$q}" placeholder="{$lang_myhr['th_hr_id']}">
    <input type="submit">
    <input type="reset" onclick="document.getElementById('q').value='';document.getElementById('filterForm').submit();">
</form>
FORM;

print $filterForm;

print("<table width='100%' id='hr-table'>");
print("<tr>
			<td class='colhead' align='center'>{$lang_myhr['th_hr_id']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_torrent_name']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_uploaded']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_downloaded']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_share_ratio']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_seed_time_required']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_completed_at']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_ttl']}</td>
			<td class='colhead' align='center'>{$lang_myhr['th_comment']}</td>
			<td class='colhead' align='center'>{$lang_functions['std_action']}</td>
			</tr>");
if ($rescount) {
    $hasActionRemove = false;
    foreach ($list as $row) {
        $columnAction = '<td class="rowfollow nowrap" align="center">';
        if ($row->uid == $CURUSER['id'] && in_array($row->status, \App\Models\HitAndRun::CAN_PARDON_STATUS)) {
            $hasActionRemove = true;
            $columnAction .= sprintf('<input class="remove-hr" type="button" value="%s" data-id="%s">', $lang_myhr['action_remove'], $row->id);
        }
        $columnAction .= '</td>';
        print("<tr>
			<td class='rowfollow nowrap' align='center'>" . $row->id . "</td>
			<td class='rowfollow' align='left'><a href='details.php?id=" . $row->torrent_id . "'>" . optional($row->torrent)->name . "</a></td>
			<td class='rowfollow nowrap' align='center'>" . \App\Support\Format::size($row->snatch->uploaded) . "</td>
			<td class='rowfollow nowrap' align='center'>" . \App\Support\Format::size($row->snatch->downloaded) . "</td>
			<td class='rowfollow nowrap' align='center'>" . \App\Support\Ratio::hr($row->snatch->uploaded, $row->snatch->downloaded) . "</td>
			<td class='rowfollow nowrap' align='center'>" . $row->seedTimeRequired . "</td>
			<td class='rowfollow nowrap' align='center'>" . \App\Support\Time::formatDateTime($row->snatch->completedat) . "</td>
			<td class='rowfollow nowrap' align='center' >" . $row->inspectTimeLeft . "</td>
                <td class='rowfollow nowrap' align='left' style='padding-left: 10px'>" . nl2br(trim($row->comment)) . "</td>
                {$columnAction}
			</tr>");
    }
    if ($hasActionRemove) {
        $msg = \App\Support\Locale::trans('hr.remove_confirm_msg', ['bonus' => $cancelHrBonus], null);
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
        \App\Support\AssetAppender::js($js, 'footer', false);
    }
}

print("</table>");
print($pagerbottom);
