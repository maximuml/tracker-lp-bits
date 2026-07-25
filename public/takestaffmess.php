<?php
require "../include/bittorrent.php";
if ($_SERVER["REQUEST_METHOD"] != "POST")
	stderr("Error", "Permission denied!");
dbconn();
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR)
	stderr("Sorry", "Permission denied.");

$sender_id = ($_POST['sender'] == 'system' ? 0 : (int)$CURUSER['id']);
$dt = date("Y-m-d H:i:s");
$msg = trim($_POST['msg']);
if (!$msg)
	stderr("Error","Don't leave any fields blank.");
$updateset = $_POST['clases'];
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
$subject = trim($_POST['subject']);
$size = 10000;
$page = 1;
set_time_limit(300);
$conditions = [];
if (!empty($_POST['classes'])) {
    $classIds = array_map('intval', $_POST['classes']);
    $conditions[] = "class IN (" . implode(', ', $classIds) . ")";
}
$conditions = apply_filter("role_query_conditions", $conditions, $_POST);
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
?>
