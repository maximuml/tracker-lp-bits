<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if ($__server_REQUEST_METHOD != "POST")
    \App\Support\LegacyResponse::abort("Error", "Permission denied!");

if (\App\Support\UserDisplay::currentClass() < UC_SYSOP)
    \App\Support\LegacyResponse::abort("Sorry", "Permission denied.");

$validTypeMap = $lang_incrementbulk['types'];
$sender_id = (\App\Support\SupportContext::getPost('sender') == 'system' ? 0 : (int)$CURUSER['id']);
$added = date("Y-m-d H:i:s");
$msg = trim(\App\Support\SupportContext::getPost('msg'));
$amount = \App\Support\SupportContext::getPost('amount');
$type = \App\Support\SupportContext::getPost('type') ?? '';
if (!$msg || !$amount || !$type)
    \App\Support\LegacyResponse::abort("Error", "Don't leave any fields blank.");
if(!is_numeric($amount))
    \App\Support\LegacyResponse::abort("Error", "amount must be numeric");
if (!(isset($validTypeMap[$type]))) {
    \App\Support\LegacyResponse::abort("Error", "Invalid type");
}
if ($type == 'uploaded') {
    $amount = \App\Support\Format::bytesFromUnit($amount,"G");
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
$conditions = \App\Support\Hooks::applyFilter("role_query_conditions", $conditions, \App\Support\SupportContext::allPost());
if (empty($conditions)) {
    \App\Support\LegacyResponse::abort("Error", "No valid filter");
}
if ($isTypeTmpInvite) {
    $duration = intval(\App\Support\SupportContext::getPost('duration') ?? 0);
    if ($duration <= 0) {
        \App\Support\LegacyResponse::abort("Sorry", "Invalid duration: $duration");
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
        $output = \App\Support\Environment::run($command, 'string', (bool) true, (bool) true);
        \App\Support\Logger::writeWithContext((string) sprintf('command: %s, output: %s', $command, $output), (string) 'info', (bool) false);
    } else {
        \Nexus\Database\NexusDB::table('users')->whereIn('id', $idArr)->increment($type, $amount);
    }
    if (!empty($msgRows)) {
        \Nexus\Database\NexusDB::table('messages')->insert($msgRows);
    }
    $page++;
}

header("Location: increment-bulk.php?sent=1&type=$type");
