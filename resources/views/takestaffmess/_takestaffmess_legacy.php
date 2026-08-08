<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
	stderr("Error", "Permission denied!");

if (get_user_class() < UC_ADMINISTRATOR)
	stderr("Sorry", "Permission denied.");

$sender_id = (\App\Support\SupportContext::getPost('sender') == 'system' ? 0 : (int)$CURUSER['id']);
$dt = date("Y-m-d H:i:s");
$msg = trim(\App\Support\SupportContext::getPost('msg'));
if (!$msg)
	stderr("Error","Don't leave any fields blank.");
$updateset = \App\Support\SupportContext::getPost('clases');
if (is_array($updateset)) {
	foreach ($updateset as &$class) {
        $class=intval($class);
		if (!is_valid_id($class) && $class != 0)
			stderr("Error","Invalid Class");
	}
}else{
	if (!is_valid_id($updateset) && $updateset != 0)
		stderr("Error","Invalid Class");
}
$subject = trim(\App\Support\SupportContext::getPost('subject'));
$size = 10000;
$page = 1;
set_time_limit(300);
$conditions = [];
if (!empty(\App\Support\SupportContext::getPost('classes'))) {
    $classIds = array_map('intval', \App\Support\SupportContext::getPost('classes'));
    $conditions[] = "class IN (" . implode(', ', $classIds) . ")";
}
$conditions = apply_filter("role_query_conditions", $conditions, \App\Support\SupportContext::allPost());
if (empty($conditions)) {
    stderr("Error","No valid filter");
}
$whereStr = implode(' OR ', $conditions);
while (true) {
    $msgRecords = [];
    $offset = ($page - 1) * $size;
    $rows = \Nexus\Database\NexusDB::table('users')
        ->whereRaw("($whereStr)")
        ->where('enabled', 'yes')
        ->where('status', 'confirmed')
        ->offset($offset)
        ->limit($size)
        ->get(['id']);
    foreach ($rows as $dat)
    {
        $msgRecords[] = [
            'sender' => $sender_id,
            'receiver' => $dat->id,
            'added' => $dt,
            'subject' => $subject,
            'msg' => $msg,
        ];
    }
    if (empty($msgRecords)) {
        break;
    }
    \App\Models\Message::query()->insert($msgRecords);
    $page++;
}

header("Location: staffmess.php?sent=1");
return;
