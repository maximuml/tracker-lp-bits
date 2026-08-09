<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$uid = \App\Support\SupportContext::getRequestInput('uid') ?? $CURUSER['id'] ?? 0;
int_check($uid,true);
$user = \App\Models\User::query()->where('id', $uid)->first(\App\Models\User::$commonFields);
if (!$user) {
    stderr("Error", "Invalid uid: $uid");
}
if ($uid != $CURUSER['id']) {
    user_can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY->value, true, $CURUSER['id']);
}
$isRecordSeedingBonusLog = \App\Models\Setting::getIsRecordSeedingBonusLog();
$defaultCategory = \App\Models\BonusLogs::CATEGORY_COMMON;
$category = \App\Support\SupportContext::getRequestInput('category') ?? $defaultCategory;
$categoryOptions = \App\Models\BonusLogs::listCategoryOptions($isRecordSeedingBonusLog);
if (!(isset($categoryOptions[$category]))) {
    stderr("Error", "Invalid category: $category");
}
$businessType = \App\Support\SupportContext::getRequestInput('business_type') ?? 0;
$businessTypeOptions = \App\Models\BonusLogs::listBusinessTypeOptions($isRecordSeedingBonusLog ? '' : $defaultCategory);
if ($businessType && !(isset($businessTypeOptions[$businessType]))) {
    stderr("Error", "Invalid business_type: $businessType");
}

stdhead(nexus_trans('bonus-log.title_for_user'));
$pagerParam = "?uid=$uid&category=$category&business_type=$businessType";
print("<h1 align=center>".nexus_trans('bonus-log.title_for_user') . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");

$textSelectOnePlease = nexus_trans('nexus.select_one_please');
$categoryOptionsText = $businessTypeOptionsText = '';
foreach ($categoryOptions as $name => $text) {
    $categoryOptionsText .= sprintf(
        '<option value="%s"%s>%s</option>',
        $name, ((\App\Support\SupportContext::getRequestInput('category') !== null)) && \App\Support\SupportContext::getRequestInput('category') == $name ? ' selected' : '', $text
    );
}
foreach ($businessTypeOptions as $name => $text) {
    $businessTypeOptionsText .= sprintf(
        '<option value="%s"%s>%s</option>',
        $name, ((\App\Support\SupportContext::getRequestInput('business_type') !== null)) && \App\Support\SupportContext::getRequestInput('business_type') == $name ? ' selected' : '', $text
    );
}

$resetText = nexus_trans('label.reset');
$submitText = nexus_trans('label.submit');
$categoryText = nexus_trans('bonus-log.category');
$businessTypeText = nexus_trans('bonus-log.fields.business_type');
$filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$__server_REQUEST_URI}" method="get">
        <input type="hidden" name="uid" value="{$uid}" />
        <span>{$categoryText}:</span>
        <select name="category">
            {$categoryOptionsText}
        </select>
        &nbsp;&nbsp;
        <span>{$businessTypeText}:</span>
        <select name="business_type">
            <option value="0">-{$textSelectOnePlease}-</option>
            {$businessTypeOptionsText}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$submitText}">
        <input type="button" id="reset" value="{$resetText}">
    </form>
</div>
FORM;
$resetJs = <<<JS
jQuery("#reset").on('click', function () {
    jQuery("select[name=category]").val('')
    jQuery("select[name=business_type]").val('')
})
JS;
\Nexus\Nexus::js($resetJs, 'footer', false);

$rep = new \App\Repositories\BonusRepository();
$total = $rep->getCount($category, $uid, $businessType);
list($pagertop, $pagerbottom, $limit, $offset, $pageSize, $page) = pager(50, $total, "$pagerParam&");
$list = $rep->getList($category, $uid, $businessType, $page + 1, $pageSize);
if (empty($nexus_legacy_layout)) { begin_main_frame(); }
print($filterForm);
print("<table id='bonus-log-table' width='100%' cellpadding='5'>");
print("<tr>
    <td class='colhead' align='left'>".nexus_trans('bonus-log.fields.business_type')."</td>
    <td class='colhead' align='left'>".nexus_trans('bonus-log.fields.old_total_value')."</td>
    <td class='colhead' align='left'>".nexus_trans('bonus-log.fields.value')."</td>
    <td class='colhead' align='left'>".nexus_trans('bonus-log.fields.new_total_value')."</td>
    <td class='colhead' align='left'>".nexus_trans('label.comment')."</td>
    <td class='colhead' align='left'>".nexus_trans('label.created_at')."</td>
</tr>");
foreach ($list as $row) {
    print("<tr>
        <td class='rowfollow nowrap' align='left'>" . $row->businessTypeText . "</td>
        <td class='rowfollow nowrap' align='left'>" . ($row->old_total_value > 0 ? number_format($row->old_total_value, 1) : '-') . "</td>
        <td class='rowfollow nowrap' align='left'>" . ($row->old_total_value < $row->new_total_value ? "+" . number_format($row->value, 1) : "-" . number_format($row->value, 1)) . "</td>
        <td class='rowfollow nowrap' align='left'>" . ($row->new_total_value > 0 ? number_format($row->new_total_value, 1) : '-') . "</td>
        <td class='rowfollow nowrap' align='left'>" . $row->comment . "</td>
        <td class='rowfollow nowrap' align='left'>" . $row->created_at . "</td>
    </tr>");
}

print("</table>");
print($pagerbottom);
if (empty($nexus_legacy_layout)) { end_main_frame(); }
if (empty($nexus_legacy_layout)) { stdfoot(); }


