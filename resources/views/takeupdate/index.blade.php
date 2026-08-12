@php
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER);
if (empty(\App\Support\SupportContext::getPost('delreport'))) {
    \App\Support\LegacyResponse::abort('Error', $lang_functions['select_at_least_one_record']);
}
if (\App\Support\SupportContext::getPost('setdealt')){
    \Nexus\Database\NexusDB::table('reports')
        ->whereIn('id', \App\Support\SupportContext::getPost('delreport'))
        ->where('dealtwith', 0)
        ->update(['dealtwith' => 1, 'dealtby' => $CURUSER['id']]);
    $Cache->delete_value('staff_new_report_count');
}
elseif (\App\Support\SupportContext::getPost('delete')){
    \Nexus\Database\NexusDB::table('reports')->whereIn('id', \App\Support\SupportContext::getPost('delreport'))->delete();
    $Cache->delete_value('staff_new_report_count');
    $Cache->delete_value('staff_report_count');
}

header("Location: reports.php");
@endphp
