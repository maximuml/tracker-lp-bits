<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);



$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$uid = \App\Support\SupportContext::getRequestInput('uid') ?? $CURUSER['id'] ?? 0;
\App\Support\LegacyResponse::assertId($uid, true);
$user = \App\Models\User::query()->where('id', $uid)->first(\App\Models\User::$commonFields);
if (!$user) {
    \App\Support\LegacyResponse::abort("Error", "Invalid uid: $uid");
}
if ($uid != $CURUSER['id']) {
    \App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY, $user);
}
$isRecordSeedingBonusLog = \App\Models\Setting::getIsRecordSeedingBonusLog();
$defaultCategory = \App\Models\BonusLogs::CATEGORY_COMMON;
$category = \App\Support\SupportContext::getRequestInput('category') ?? $defaultCategory;
$categoryOptions = \App\Models\BonusLogs::listCategoryOptions($isRecordSeedingBonusLog);
if (!(isset($categoryOptions[$category]))) {
    \App\Support\LegacyResponse::abort("Error", "Invalid category: $category");
}
$businessType = \App\Support\SupportContext::getRequestInput('business_type') ?? 0;
$businessTypeOptions = \App\Models\BonusLogs::listBusinessTypeOptions($isRecordSeedingBonusLog ? '' : $defaultCategory);
if ($businessType && !(isset($businessTypeOptions[$businessType]))) {
    \App\Support\LegacyResponse::abort("Error", "Invalid business_type: $businessType");
}

\App\Support\Html::stdhead(\App\Support\Locale::trans('bonus-log.title_for_user', [], null));
$pagerParam = "?uid=$uid&category=$category&business_type=$businessType";
print("<h1 align=center>".\App\Support\Locale::trans('bonus-log.title_for_user', [], null) . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");

$textSelectOnePlease = \App\Support\Locale::trans('nexus.select_one_please', [], null);
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

$resetText = \App\Support\Locale::trans('label.reset', [], null);
$submitText = \App\Support\Locale::trans('label.submit', [], null);
$categoryText = \App\Support\Locale::trans('bonus-log.category', [], null);
$businessTypeText = \App\Support\Locale::trans('bonus-log.fields.business_type', [], null);
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
list($pagertop, $pagerbottom, $limit, $offset, $pageSize, $page) = \App\Support\Pagination::pager(50, $total, "$pagerParam&");
$list = $rep->getList($category, $uid, $businessType, $page + 1, $pageSize);
\App\Support\Frame::mainFrameOpen();
print($filterForm);
print("<table id='bonus-log-table' width='100%' cellpadding='5'>");
print("<tr>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('bonus-log.fields.business_type', [], null)."</td>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('bonus-log.fields.old_total_value', [], null)."</td>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('bonus-log.fields.value', [], null)."</td>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('bonus-log.fields.new_total_value', [], null)."</td>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('label.comment', [], null)."</td>
    <td class='colhead' align='left'>".\App\Support\Locale::trans('label.created_at', [], null)."</td>
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
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();


