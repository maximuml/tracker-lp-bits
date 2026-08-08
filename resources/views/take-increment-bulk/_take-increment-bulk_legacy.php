<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
    stderr("Error", "Permission denied!");

if (get_user_class() < UC_SYSOP)
    stderr("Sorry", "Permission denied.");

$validTypeMap = $lang_incrementbulk['types'];
$sender_id = (\App\Support\SupportContext::getPost('sender') == 'system' ? 0 : (int)$CURUSER['id']);
$added = date("Y-m-d H:i:s");
$msg = trim(\App\Support\SupportContext::getPost('msg'));
$amount = \App\Support\SupportContext::getPost('amount');
$type = \App\Support\SupportContext::getPost('type') ?? '';
if (!$msg || !$amount || !$type)
    stderr("Error","Don't leave any fields blank.");
if(!is_numeric($amount))
    stderr("Error","amount must be numeric");
if (!(isset($validTypeMap[$type]))) {
    stderr("Error","Invalid type");
}
if ($type == 'uploaded') {
    $amount = getsize_int($amount,"G");
}
$isTypeTmpInvite = $type == 'tmp_invites';
$subject = trim(\App\Support\SupportContext::getPost('subject'));
$duration = 0;
$size = 2000;
$page = 1;
set_time_limit(300);
$conditions = [];
if (!empty(\App\Support\SupportContext::getPost('classes'))) {
    $conditions[] = "class IN (" . implode(', ', \App\Support\SupportContext::getPost('classes')) . ")";
}
$conditions = apply_filter("role_query_conditions", $conditions, \App\Support\SupportContext::allPost());
if (empty($conditions)) {
    stderr("Error","No valid filter");
}
if ($isTypeTmpInvite) {
    $duration = intval(\App\Support\SupportContext::getPost('duration') ?? 0);
    if ($duration <= 0) {
        stderr("Sorry", "Invalid duration: $duration");
    }
}
$whereStr = implode(' OR ', $conditions);
while (true) {
    $msgRows = $idArr = [];
    $offset = ($page - 1) * $size;
    $users = \Nexus\Database\NexusDB::table('users')
        ->whereRaw("($whereStr)")
        ->where('enabled', 'yes')
        ->where('status', 'confirmed')
        ->offset($offset)
        ->limit($size)
        ->get(['id']);
    foreach ($users as $userRow) {
        $id = $userRow->id;
        $idArr[] = $id;
        $msgRows[] = [
            'sender' => $sender_id,
            'receiver' => $id,
            'added' => $added,
            'subject' => $subject,
            'msg' => $msg,
        ];
    }
    if (empty($idArr)) {
        break;
    }
    $idStr = implode(',', $idArr);
    $idRedisKey = sprintf("temporary_invite:%s", microtime(true));
    \Nexus\Database\NexusDB::cache_put($idRedisKey, $idStr);
    if ($isTypeTmpInvite) {
        $command = sprintf(
            'invite:tmp %s %s %s',
            $idRedisKey, $duration, $amount
        );
        $output = executeCommand($command, 'string', true);
        do_log(sprintf('command: %s, output: %s', $command, $output));
    } else {
        \Nexus\Database\NexusDB::table('users')->whereIn('id', $idArr)->increment($type, $amount);
    }
    if (!empty($msgRows)) {
        \Nexus\Database\NexusDB::table('messages')->insert($msgRows);
    }
    $page++;
}

header("Location: increment-bulk.php?sent=1&type=$type");
