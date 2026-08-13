@php
$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
	\App\Support\LegacyResponse::abort("Error", "Permission denied!");

if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR)
	\App\Support\LegacyResponse::abort("Sorry", "Permission denied.");

$sender_id = (\App\Support\SupportContext::getPost('sender') == 'system' ? 0 : (int)$CURUSER['id']);
$dt = date("Y-m-d H:i:s");
$msg = trim(\App\Support\SupportContext::getPost('msg'));
if (!$msg)
	\App\Support\LegacyResponse::abort("Error", "Don't leave any fields blank.");
$updateset = \App\Support\SupportContext::getPost('clases');
if (is_array($updateset)) {
	foreach ($updateset as &$class) {
        $class=intval($class);
		if (!\App\Support\Validators::isId($class) && $class != 0)
			\App\Support\LegacyResponse::abort("Error", "Invalid Class");
	}
}else{
	if (!\App\Support\Validators::isId($updateset) && $updateset != 0)
		\App\Support\LegacyResponse::abort("Error", "Invalid Class");
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
$conditions = \App\Support\Hooks::applyFilter("role_query_conditions", $conditions, \App\Support\SupportContext::allPost());
if (empty($conditions)) {
    \App\Support\LegacyResponse::abort("Error", "No valid filter");
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
@endphp
