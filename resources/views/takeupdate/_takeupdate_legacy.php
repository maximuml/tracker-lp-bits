<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

function bark($msg) {
 stdhead();
   stdmsg("Failed", $msg);
 stdfoot();
 return;
}
user_can('staffmem', true);
if (empty(\App\Support\SupportContext::getPost('delreport'))) {
    stderr('Error', $lang_functions['select_at_least_one_record']);
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
